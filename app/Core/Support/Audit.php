<?php

namespace App\Core\Support;

use App\Core\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class Audit
{
    public static function log(string $event, ?Model $subject = null, ?string $label = null, array $meta = []): void
    {
        $user = auth()->user();

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'event' => $event,
            'subject_type' => $subject ? Str::snake(class_basename($subject)) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $label ? mb_substr($label, 0, 190) : null,
            'meta' => $meta ?: null,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}