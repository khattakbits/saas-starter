<?php

namespace App\Core\Models\Concerns;

use App\Core\Models\Attachment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @method static void deleted(\Closure|string $callback)
 */
trait HasAttachments
{
    public static function bootHasAttachments(): void
    {
        static::deleted(function ($model) {
            // Soft delete par files rehti hain (restore ho sakta hai)
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            /** @var Collection<int, Attachment> $attachments */
            $attachments = Attachment::query()
                ->where('attachable_type', $model->attachmentType())
                ->where('attachable_id', $model->getKey())
                ->get();

            $attachments->each(function (Attachment $a): void {
                Storage::delete($a->path);
                $a->delete();
            });
        });
    }

    public function attachmentType(): string
    {
        return Str::snake(class_basename($this));
    }
}
