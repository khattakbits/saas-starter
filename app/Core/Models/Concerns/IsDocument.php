<?php

namespace App\Core\Models\Concerns;

use App\Core\Models\DeletedDocument;
use App\Core\Support\DocumentRegistry;
use DateTimeInterface;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use KhattakBits\Core\Exceptions\DocumentException;
use KhattakBits\Core\Support\Audit;

/**
 * Table mein DocumentColumns::add() ke columns chahiye.
 * Doctype ke andar override karne ke hooks: beforeSubmit, afterSubmit, beforeCancel, afterCancel, lockedAllowed.
 */
trait IsDocument
{
    use HasAttachments, SoftDeletes;

    public const DRAFT = 0;

    public const SUBMITTED = 1;

    public const CANCELLED = 2;

    /** "updating" par capture ki hui purani values (doc.updated ke changes ke liye) */
    protected ?array $docAuditBefore = null;

    public static function bootIsDocument(): void
    {
        static::creating(function ($doc) {
            $doc->docstatus = $doc->docstatus ?? self::DRAFT;
            $doc->created_by = $doc->created_by ?? auth()->id();
        });

        // Lifecycle audit yahin se hota hai: controllers ko alag se log nahi karna padta
        static::created(function ($doc) {
            Audit::log('doc.created', $doc, $doc->docTitle(), $doc->docCreatedMeta());
        });

        static::updating(function ($doc) {
            // Draft edit ke liye purani values yaad rakho (updated event mein compare hongi)
            if ((int) $doc->getOriginal('docstatus') === self::DRAFT) {
                $doc->docAuditBefore = $doc->docAuditSnapshotFrom($doc->getOriginal());

                return;
            }

            // Submitted/Cancelled ko normal save() se badla nahi ja sakta
            $illegal = array_diff(array_keys($doc->getDirty()), $doc->lockedAllowed());
            if ($illegal) {
                throw new DocumentException("This document is {$doc->docStatusLabel()} and cannot be edited.");
            }
        });

        // Sirf Draft ke asli edits log hote hain (submit/cancel query-builder se chalte hain, wo event nahi dete)
        static::updated(function ($doc) {
            $before = $doc->docAuditBefore;
            $doc->docAuditBefore = null;

            if ($before === null || (int) $doc->getOriginal('docstatus') !== self::DRAFT) {
                return;
            }

            $changes = Audit::changes($before, $doc->docAuditSnapshot());

            if ($changes) {   // sirf updated_at badla ho to koi entry nahi
                Audit::log('doc.updated', $doc, $doc->docTitle(), ['changes' => $changes]);
            }
        });

        static::deleting(function ($doc) {
            if (! $doc->isForceDeleting() && (int) $doc->docstatus === self::SUBMITTED) {
                throw new DocumentException('A submitted document cannot be deleted. Cancel it first.');
            }
        });

        static::deleted(function ($doc) {
            if ($doc->isForceDeleting()) {
                DeletedDocument::where('doc_type', $doc->docType())->where('doc_id', $doc->getKey())->delete();

                return;
            }

            DeletedDocument::updateOrCreate(
                ['doc_type' => $doc->docType(), 'doc_id' => $doc->getKey()],
                [
                    'title' => mb_substr($doc->docTitle(), 0, 190),
                    'deleted_by' => auth()->id(),
                    'deleted_by_name' => auth()->user()?->name,
                    'original_docstatus' => (int) $doc->docstatus,
                    'created_at' => now(),
                ],
            );
            Audit::log('doc.deleted', $doc, $doc->docTitle());
        });
    }

    public function docType(): string
    {
        return app(DocumentRegistry::class)->typeOf(static::class);
    }

    public function docTitle(): string
    {
        return (string) ($this->name ?? '#'.$this->getKey());
    }

    public function docStatusLabel(): string
    {
        return ['Draft', 'Submitted', 'Cancelled'][(int) $this->docstatus] ?? 'Unknown';
    }

    /** Submitted/Cancelled hone ke baad bhi jo columns badal sakte hain (jaise payment status) */
    protected function lockedAllowed(): array
    {
        return ['updated_at', 'deleted_at'];
    }

    protected function beforeSubmit(): void {}

    protected function afterSubmit(): void {}

    protected function beforeCancel(): void {}

    protected function afterCancel(): void {}

    /** Amend ki nayi copy: created entry ko batana hota hai ke wo kis se bani hai */
    protected function docCreatedMeta(): array
    {
        if (! $this->amended_from) {
            return [];
        }

        $from = static::withTrashed()->whereKey($this->amended_from)->value('name');

        return $from ? ['amended_from' => $from] : [];
    }

    /** Audit ke liye model ki current (cast ho chuki) values */
    protected function docAuditSnapshot(): array
    {
        $out = [];

        foreach (array_keys($this->getAttributes()) as $key) {
            if (in_array($key, $this->docAuditIgnored(), true)) {
                continue;
            }
            $out[$key] = $this->docAuditValue($key, $this->getAttribute($key));
        }

        return $out;
    }

    /** Wohi snapshot, magar raw DB values se (save se pehle ki halat) */
    protected function docAuditSnapshotFrom(array $raw): array
    {
        return static::newFromBuilder($raw)->docAuditSnapshot();
    }

    /** Carbon ko string bana deta hai taake changes clean scalar from/to rahen */
    protected function docAuditValue(string $key, mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return ($this->getCasts()[$key] ?? null) === 'date'
                ? $value->format('Y-m-d')
                : $value->format('Y-m-d H:i:s');
        }

        return $value;
    }

    protected function docAuditIgnored(): array
    {
        return ['updated_at'];
    }

    /** Atomic: sirf tab chalta hai jab document abhi bhi $from halat mein ho */
    private function transition(int $from, array $to, string $error): void
    {
        $changed = static::query()->whereKey($this->getKey())->where('docstatus', $from)->update($to);

        if ($changed === 0) {
            throw new DocumentException($error);
        }
        $this->refresh();
    }

    public function submitDoc(): void
    {
        DB::transaction(function () {
            $this->beforeSubmit();
            $this->transition(self::DRAFT, ['docstatus' => self::SUBMITTED, 'submitted_at' => now()], 'Only a draft can be submitted.');
            $this->afterSubmit();
            Audit::log('doc.submitted', $this, $this->docTitle());
        });
    }

    public function cancelDoc(): void
    {
        DB::transaction(function () {
            $this->beforeCancel();
            $this->transition(self::SUBMITTED, ['docstatus' => self::CANCELLED, 'cancelled_at' => now()], 'Only a submitted document can be cancelled.');
            $this->afterCancel();
            Audit::log('doc.cancelled', $this, $this->docTitle());
        });
    }

    /** Cancelled document ka naya Draft copy (DEMO-00001 se DEMO-00001-1) */
    public function amend(): static
    {
        return DB::transaction(function () {
            if ((int) $this->docstatus !== self::CANCELLED) {
                throw new DocumentException('Only a cancelled document can be amended.');
            }
            if (static::withTrashed()->where('amended_from', $this->getKey())->exists()) {
                throw new DocumentException('This document has already been amended.');
            }

            $root = $this;
            while ($root->amended_from && ($parent = static::withTrashed()->find($root->amended_from))) {
                $root = $parent;
            }
            $n = static::withTrashed()->where('name', 'like', $root->name.'-%')->count() + 1;

            $copy = $this->replicate(['name', 'docstatus', 'submitted_at', 'cancelled_at', 'deleted_at', 'amended_from', 'created_by']);
            $copy->forceFill([
                'name' => $root->name.'-'.$n,
                'docstatus' => self::DRAFT,
                'amended_from' => $this->getKey(),
            ]);
            $copy->save();   // created event khud doc.created likhta hai (meta: amended_from)

            // Entry purane document par lagti hai: "Amended to <copy>"
            Audit::log('doc.amended', $this, $this->docTitle(), ['amended_to' => $copy->name]);

            return $copy;
        });
    }

    /** Recycle Bin se wapsi: hamesha Draft mein */
    public function restoreAsDraft(): void
    {
        DB::transaction(function () {
            $was = $this->docStatusLabel();

            $this->forceFill([
                'docstatus' => self::DRAFT,
                'submitted_at' => null,
                'cancelled_at' => null,
                'deleted_at' => null,
            ])->saveQuietly();

            DeletedDocument::where('doc_type', $this->docType())->where('doc_id', $this->getKey())->delete();
            Audit::log('doc.restored', $this, $this->docTitle(), ['was' => $was]);
        });
    }
}
