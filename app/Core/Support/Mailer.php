<?php

namespace App\Core\Support;

use App\Core\Jobs\SendEmailJob;
use App\Core\Models\EmailAccount;
use App\Core\Models\EmailLog;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class Mailer
{
    private const READY_KEY = 'mail.ready';

    private const READY_TTL = 300;

    /** Email settings: values + validation (settings UI baad mein inhi ko use karega) */
    public static function settings(): array
    {
        $store = app(SettingsStore::class);

        return [
            'email_enabled' => filter_var($store->get('email_enabled', '1'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
            'email_reply_to' => (string) $store->get('email_reply_to', ''),
            'email_footer' => (string) $store->get('email_footer', ''),
            'email_daily_limit' => (int) $store->get('email_daily_limit', 500),
            'email_log_days' => (int) $store->get('email_log_days', 90),
        ];
    }

    public static function rules(): array
    {
        return [
            'email_enabled' => ['boolean'],
            'email_reply_to' => ['nullable', 'email', 'max:190'],
            'email_footer' => ['nullable', 'string', 'max:500'],
            'email_daily_limit' => ['required', 'integer', 'min:0', 'max:100000'],
            'email_log_days' => ['required', 'integer', 'in:0,30,90,365'],
        ];
    }

    /**
     * Log bana kar job queue karta hai. Body kahin store nahi hoti —
     * job ko sirf log id + rendered subject/text jata hai.
     */
    public static function send(string $to, string $subject, string $text, array $opts = []): EmailLog
    {
        // Limit pehle ginte hain warna apni hi "queued" row ginti mein aa jati hai
        $blocked = self::blockedReason();

        $log = EmailLog::create([
            'email_account_id' => null,
            'to_email' => mb_substr($to, 0, 190),
            'subject' => mb_substr($subject, 0, 255),
            'template_key' => $opts['template_key'] ?? null,
            'status' => 'queued',
            'attempts' => 0,
            'user_id' => $opts['user_id'] ?? auth()->id(),
            'queued_at' => now(),
            'created_at' => now(),
        ]);

        if ($blocked !== null) {
            return self::fail($log, $blocked);
        }

        $account = self::defaultAccount();
        if ($account) {
            $log->forceFill(['email_account_id' => $account->getKey()])->save();
        }

        try {
            SendEmailJob::dispatch($log->getKey(), mb_substr($subject, 0, 255), $text);
        } catch (Throwable $e) {
            // sync queue: job ne khud log update kiya hota hai, yahan sirf safety net
            self::failIfQueued($log, $e, $account);
        }

        return $log;
    }

    /** Account ka transport bana kar message bhejta hai (job aur test-send dono yahi use karte hain) */
    public static function deliver(?EmailAccount $account, string $to, string $subject, string $text): void
    {
        $mailer = $account ? Mail::build(self::configFor($account)) : Mail::mailer();

        $html = self::html($text);
        $fromEmail = $account?->from_email ?: (string) config('mail.from.address');
        $fromName = $account?->from_name ?: (string) config('mail.from.name');
        $replyTo = self::settings()['email_reply_to'];

        $mailer->send([], [], function (Message $message) use ($to, $subject, $text, $html, $fromEmail, $fromName, $replyTo) {
            $message->to($to)->subject($subject)->from($fromEmail, $fromName)->text($text)->html($html);
            if ($replyTo !== '') {
                $message->replyTo($replyTo);
            }
        });
    }

    /**
     * Runtime SMTP config. 'ssl' = implicit TLS (smtps, usually 465);
     * 'tls' aur 'none' = plain smtp, STARTTLS transport khud negotiate karta hai.
     */
    public static function configFor(EmailAccount $account): array
    {
        return [
            'transport' => 'smtp',
            'scheme' => $account->encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $account->host,
            'port' => (int) $account->port,
            'username' => $account->username,
            'password' => $account->password,
            'timeout' => 15,
            'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
        ];
    }

    /** Synchronous test-send: queue bypass, last_tested_at/last_test_ok update, EmailLog 'test' */
    public static function sendTest(EmailAccount $account, string $to): array
    {
        $rendered = app(EmailTemplates::class)->render('test', [
            'app_name' => (string) app(SettingsStore::class)->get('app_name', config('app.name')),
            'user_name' => auth()->user()?->name ?? $to,
        ]);

        $subject = $rendered['subject'];
        $text = $rendered['text'];

        $log = EmailLog::create([
            'email_account_id' => $account->getKey(),
            'to_email' => mb_substr($to, 0, 190),
            'subject' => mb_substr($subject, 0, 255),
            'template_key' => 'test',
            'status' => 'queued',
            'attempts' => 0,
            'user_id' => auth()->id(),
            'queued_at' => now(),
            'created_at' => now(),
        ]);

        try {
            self::deliver($account, $to, $subject, $text);
            $log->forceFill(['status' => 'sent', 'error' => null, 'attempts' => 1, 'sent_at' => now()])->save();
            $account->forceFill(['last_tested_at' => now(), 'last_test_ok' => true])->save();

            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            $error = self::sanitize($e->getMessage(), $account);
            $log->forceFill(['status' => 'failed', 'error' => $error, 'attempts' => 1])->save();
            $account->forceFill(['last_tested_at' => now(), 'last_test_ok' => false])->save();

            return ['ok' => false, 'error' => $error];
        }
    }

    public static function defaultAccount(): ?EmailAccount
    {
        return EmailAccount::query()->where('is_active', true)->where('is_default', true)->first();
    }

    /** Email bhejne ke liye tayyar hai? (account ya framework ka real mailer) */
    public static function isReady(): bool
    {
        return (bool) Cache::remember(self::READY_KEY, self::READY_TTL, function () {
            if (! self::settings()['email_enabled']) {
                return false;
            }
            if (self::defaultAccount() !== null) {
                return true;
            }

            return ! in_array(config('mail.default'), ['log', 'array'], true);
        });
    }

    public static function forgetReady(): void
    {
        Cache::forget(self::READY_KEY);
    }

    /** Error message se account ke secrets hata deta hai (max 500 chars) */
    public static function sanitize(string $message, ?EmailAccount $account = null): string
    {
        $message = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);

        foreach ([$account?->password, $account?->username] as $secret) {
            if (is_string($secret) && $secret !== '') {
                $message = str_replace($secret, '[redacted]', $message);
            }
        }

        return mb_substr($message, 0, 500);
    }

    /** Plain text -> simple HTML (escaped paragraphs + auto-linked URLs + footer) */
    public static function html(string $text): string
    {
        $app = e((string) app(SettingsStore::class)->get('app_name', config('app.name')));
        $footer = trim(self::settings()['email_footer']);

        $body = '';
        foreach (preg_split('/\R{2,}/u', trim($text)) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            $body .= '<p style="margin:0 0 12px">'.self::linkify(e($paragraph)).'</p>';
        }

        return '<!doctype html><html><body style="margin:0;padding:24px;background:#f7f8fa;'
            .'font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#111827">'
            .'<div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;padding:20px">'
            .'<p style="margin:0 0 16px;font-weight:600">'.$app.'</p>'
            .$body
            .($footer !== '' ? '<hr style="border:0;border-top:1px solid #e5e7eb;margin:16px 0"><p style="margin:0;font-size:12px;color:#4b5563">'.e($footer).'</p>' : '')
            .'</div></body></html>';
    }

    private static function blockedReason(): ?string
    {
        if (! self::settings()['email_enabled']) {
            return 'Email is turned off';
        }
        if (self::dailyLimitReached()) {
            return 'Daily limit reached';
        }

        return null;
    }

    private static function dailyLimitReached(): bool
    {
        $limit = self::settings()['email_daily_limit'];
        if ($limit <= 0) {
            return false;                       // 0 = unlimited
        }

        $since = now()->utc()->startOfDay();     // created_at index use hota hai

        return EmailLog::query()
            ->where('created_at', '>=', $since)
            ->whereIn('status', ['queued', 'sent'])
            ->count() >= $limit;
    }

    private static function fail(EmailLog $log, string $message): EmailLog
    {
        $log->forceFill(['status' => 'failed', 'error' => mb_substr($message, 0, 500)])->save();

        return $log;
    }

    private static function failIfQueued(EmailLog $log, Throwable $e, ?EmailAccount $account): void
    {
        $fresh = EmailLog::query()->find($log->getKey());
        if ($fresh && $fresh->status === 'queued') {
            self::fail($fresh, self::sanitize($e->getMessage(), $account));
        }
    }

    private static function linkify(string $escaped): string
    {
        return preg_replace('~(https?://[^\s<]+)~i', '<a href="$1" style="color:#4f46e5">$1</a>', $escaped) ?? $escaped;
    }
}
