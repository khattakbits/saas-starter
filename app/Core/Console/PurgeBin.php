<?php

namespace App\Core\Console;

use App\Core\Models\DeletedDocument;
use App\Core\Support\Audit;
use App\Core\Support\DocumentRegistry;
use App\Core\Support\SettingsStore;
use Illuminate\Console\Command;

class PurgeBin extends Command
{
    protected $signature = 'bin:purge {--days= : Override the retention days}';

    protected $description = 'Recycle Bin se purane documents hamesha ke liye hataye';

    public function handle(SettingsStore $settings, DocumentRegistry $registry): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : (int) $settings->get('recycle_bin_days', 30);

        if ($days <= 0) {
            $this->info('Auto-purge is off.');

            return self::SUCCESS;
        }

        $count = 0;
        DeletedDocument::query()
            ->where('created_at', '<', now()->subDays($days))
            ->orderBy('id')
            ->chunkById(200, function ($entries) use ($registry, &$count) {
                foreach ($entries as $entry) {
                    $cfg = $registry->get($entry->doc_type);
                    $doc = $cfg ? $cfg['model']::withTrashed()->find($entry->doc_id) : null;
                    $doc?->forceDelete();                 // attachments ki files bhi hat jati hain
                    $entry->delete();
                    Audit::log('doc.purged', null, $entry->title, ['type' => $entry->doc_type, 'auto' => true]);
                    $count++;
                }
            });

        $this->info("Purged {$count} document(s).");

        return self::SUCCESS;
    }
}
