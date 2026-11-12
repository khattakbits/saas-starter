<?php

namespace App\Core\Jobs;

use App\Core\Models\EmailAccount;
use App\Core\Models\EmailLog;
use App\Core\Support\Mailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    /** Payload mein sirf log id aur rendered content — koi credential nahi */
    public function __construct(
        public int $logId,
        public string $subject,
        public string $text,
    ) {}

    public function handle(): void
    {
        $log = EmailLog::query()->find($this->logId);
        if (! $log || $log->status === 'sent') {
            return;
        }

        $account = $log->email_account_id ? EmailAccount::query()->find($log->email_account_id) : null;

        try {
            Mailer::deliver($account, $log->to_email, $this->subject, $this->text);
            $log->forceFill([
                'status' => 'sent',
                'error' => null,
                'sent_at' => now(),
                'attempts' => $log->attempts + 1,
            ])->save();
        } catch (Throwable $e) {
            $error = Mailer::sanitize($e->getMessage(), $account);

            $log->forceFill([
                'status' => 'failed',
                'error' => $error,
                'attempts' => $log->attempts + 1,
            ])->save();

            // Asli message mein password ho sakta hai; queue/failed_jobs mein sanitized version jata hai
            throw new RuntimeException($error, 0, $e);   // retry: tries 3, backoff [60, 300]
        }
    }

    public function failed(?Throwable $e): void
    {
        $log = EmailLog::query()->find($this->logId);
        if (! $log || $log->status === 'sent') {
            return;
        }

        $account = $log->email_account_id ? EmailAccount::query()->find($log->email_account_id) : null;
        $log->forceFill([
            'status' => 'failed',
            'error' => Mailer::sanitize($e?->getMessage() ?? 'The email could not be sent.', $account),
        ])->save();
    }
}
