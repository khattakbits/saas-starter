<?php

namespace App\Core\Console;

use App\Core\Models\EmailLog;
use App\Core\Support\SettingsStore;
use Illuminate\Console\Command;

class PruneEmails extends Command
{
    protected $signature = 'emails:prune {--days= : Override the retention days}';

    protected $description = 'Purane email logs delete kare (email_log_days)';

    public function handle(SettingsStore $settings): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : (int) $settings->get('email_log_days', 90);

        if ($days <= 0) {
            $this->info('Email log pruning is off.');

            return self::SUCCESS;
        }

        $cutoff = now()->utc()->subDays($days);
        $count = 0;

        EmailLog::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(500, function ($logs) use (&$count) {
                foreach ($logs as $log) {
                    $log->delete();
                    $count++;
                }
            });

        $this->info("Deleted {$count} email log(s).");

        return self::SUCCESS;
    }
}
