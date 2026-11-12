<?php

namespace App\Core\Support;

use App\Core\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class Audit
{
    /**
     * Note: method ka naam `log()` hai (task mein `record()` likha tha). Yeh aik hi
     * entry point rakha gaya hai taake 25+ call sites duplicate na hon.
     *
     * user_id FK nahi hai (users central DB mein rehte hain), is liye name + email
     * snapshot jaate hain — tenant export/restore ke baad bhi row readable rahe.
     */
    public static function log(string $event, ?Model $subject = null, ?string $label = null, array $meta = []): void
    {
        $user = auth()->user();

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user ? mb_substr((string) $user->name, 0, 100) : null,
            'user_email' => $user ? mb_substr((string) $user->email, 0, 190) : null,
            'event' => $event,
            'subject_type' => $subject
                ? (method_exists($subject, 'docType') ? $subject->docType() : Str::snake(class_basename($subject)))
                : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $label ? mb_substr($label, 0, 190) : null,
            'meta' => $meta ?: null,
            'ip' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 255) ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * Pehle/baad ke do arrays ka farq. Scalar: ['from' => .., 'to' => ..], list: ['added' => [..], 'removed' => [..]]
     */
    public static function changes(array $before, array $after): array
    {
        $out = [];

        foreach ($after as $key => $new) {
            $old = $before[$key] ?? null;

            if (is_array($new) || is_array($old)) {
                $added = array_values(array_diff((array) $new, (array) $old));
                $removed = array_values(array_diff((array) $old, (array) $new));
                if ($added || $removed) {
                    $out[$key] = ['added' => $added, 'removed' => $removed];
                }
            } elseif ($old !== $new) {
                $out[$key] = ['from' => $old, 'to' => $new];
            }
        }

        return $out;
    }
}
