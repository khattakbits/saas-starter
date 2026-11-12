<?php

namespace App\Core\Support;

use App\Core\Models\EmailTemplate;
use InvalidArgumentException;

/**
 * Email templates ki registry: defaults code mein, DB sirf overrides rakhti hai.
 * Stored text kabhi Blade/eval se nahi guzarta — sirf simple {{ token }} replace hota hai.
 */
final class EmailTemplates
{
    private const TOKEN = '/\{\{\s*([a-z_]+)\s*\}\}/';

    private ?array $definitions = null;

    public function __construct(private ModuleRegistry $modules) {}

    /** @return array<string, array> */
    public function all(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        // Modules pehle, core baad mein: core keys module se hijack nahi hotin
        $defs = [];
        foreach ($this->modules->all() as $module) {
            foreach ($module['def']['email_templates'] ?? [] as $key => $def) {
                $defs[$key] = $def;
            }
        }
        foreach (self::core() as $key => $def) {
            $defs[$key] = $def;
        }

        return $this->definitions = $defs;
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function override(string $key): ?EmailTemplate
    {
        return EmailTemplate::query()->where('key', $key)->first();
    }

    /** @return array{subject: string, text: string} */
    public function render(string $key, array $data): array
    {
        $def = $this->get($key);
        if ($def === null) {
            throw new InvalidArgumentException("Unknown email template [{$key}].");
        }

        $override = $this->override($key);
        $saved = $override && $override->is_active;

        return $this->fillText(
            $saved ? $override->subject : $def['subject'],
            $saved ? $override->body : $def['body'],
            $data,
        );
    }

    /** @return array{subject: string, text: string} */
    public function fillText(string $subject, string $body, array $data): array
    {
        return [
            'subject' => $this->oneLine($this->fill($subject, $data)),
            'text' => $this->fill($body, $data),
        ];
    }

    /**
     * Validation errors (field => messages). Koi error na ho to khali array.
     *
     * @return array<string, array<int, string>>
     */
    public function validate(string $key, string $subject, string $body): array
    {
        $def = $this->get($key);
        if ($def === null) {
            return ['key' => ['Unknown email template.']];
        }

        $errors = [];
        $subject = trim($subject);
        $body = trim($body);

        if ($subject === '') {
            $errors['subject'][] = 'Subject is required.';
        } elseif (preg_match('/[\r\n]/', $subject)) {
            $errors['subject'][] = 'Subject cannot contain line breaks.';
        } elseif (mb_strlen($subject) > 200) {
            $errors['subject'][] = 'Subject may not be longer than 200 characters.';
        }

        if ($body === '') {
            $errors['body'][] = 'Body is required.';
        } elseif (mb_strlen($body) > 5000) {
            $errors['body'][] = 'Body may not be longer than 5000 characters.';
        }

        $allowed = array_keys($def['placeholders'] ?? []);

        $unknownSubject = array_values(array_diff($this->tokens($subject), $allowed));
        if ($unknownSubject) {
            $errors['subject'][] = 'Unknown placeholder(s): '.$this->listTokens($unknownSubject).'.';
        }

        $unknownBody = array_values(array_diff($this->tokens($body), $allowed));
        if ($unknownBody) {
            $errors['body'][] = 'Unknown placeholder(s): '.$this->listTokens($unknownBody).'.';
        }

        $missing = array_values(array_diff($def['required'] ?? [], $this->tokens($subject."\n".$body)));
        if ($missing) {
            $errors['body'][] = 'Required placeholder(s) missing: '.$this->listTokens($missing).'.';
        }

        return $errors;
    }

    /** Preview/test ke liye sample values */
    public function sampleData(string $key): array
    {
        $def = $this->get($key) ?? [];
        $data = $def['samples'] ?? [];
        $data['app_name'] = (string) app(SettingsStore::class)->get('app_name', config('app.name'));

        if (array_key_exists('user_name', $def['placeholders'] ?? []) && auth()->check()) {
            $data['user_name'] = auth()->user()->name;
        }

        return $data;
    }

    /** @return string[] */
    public function tokens(string $text): array
    {
        preg_match_all(self::TOKEN, $text, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    private function fill(string $text, array $data): string
    {
        // Sirf plain text replacement — koi Blade/eval nahi. Unknown token khali ho jata hai.
        return preg_replace_callback(self::TOKEN, function (array $match) use ($data) {
            $value = $data[$match[1]] ?? '';

            return is_scalar($value) ? (string) $value : '';
        }, $text) ?? $text;
    }

    private function oneLine(string $text): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', $text) ?? $text);
    }

    private function listTokens(array $tokens): string
    {
        return implode(', ', array_map(fn (string $t): string => '{{ '.$t.' }}', $tokens));
    }

    /** @return array<string, array> */
    private static function core(): array
    {
        return [
            'password_reset' => [
                'name' => 'Password reset',
                'subject' => 'Reset your {{ app_name }} password',
                'body' => "Hi {{ user_name }},\n\nSomeone asked to reset the password of your {{ app_name }} account. Open the link below to choose a new one:\n\n{{ reset_url }}\n\nThe link stops working in {{ expires_minutes }} minutes. If you did not ask for this, you can ignore this email.",
                'placeholders' => [
                    'user_name' => 'Name of the person receiving the email',
                    'app_name' => 'Workspace name from the settings',
                    'reset_url' => 'Link that opens the password reset form',
                    'expires_minutes' => 'How long the link stays valid, in minutes',
                ],
                'required' => ['reset_url'],
                'samples' => [
                    'user_name' => 'Ayesha Khan',
                    'reset_url' => 'https://example.com/reset/8f2c1d',
                    'expires_minutes' => '60',
                ],
            ],
            'test' => [
                'name' => 'Test email',
                'subject' => 'Test email from {{ app_name }}',
                'body' => "Hi {{ user_name }},\n\nThis is a test email from {{ app_name }}.\n\nIf you can read this message, the mail account is configured correctly.",
                'placeholders' => [
                    'app_name' => 'Workspace name from the settings',
                    'user_name' => 'Name of the person receiving the email',
                ],
                'required' => [],
                'samples' => [
                    'user_name' => 'Ayesha Khan',
                ],
            ],
        ];
    }
}
