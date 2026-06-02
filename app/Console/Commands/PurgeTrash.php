<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PurgeTrash extends Command
{
    protected $signature = 'reminders:purge-trash {--days=30}';

    protected $description = 'Permanently delete reminders that have been in the trash for more than N days.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        $count = Reminder::onlyTrashed()->where('deleted_at', '<', $cutoff)->count();
        Reminder::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();

        $this->info("Purged {$count} reminder(s) deleted before {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
