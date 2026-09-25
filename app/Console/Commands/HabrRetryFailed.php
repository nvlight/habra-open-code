<?php

namespace App\Console\Commands;

use App\Models\HabrSource;
use Illuminate\Console\Command;

class HabrRetryFailed extends Command
{
    protected $signature = 'habr:retry-failed';

    protected $description = 'Reset failed habr sources back to pending so they are retried';

    public function handle(): int
    {
        $count = HabrSource::query()
            ->where('status', HabrSource::STATUS_FAILED)
            ->update(['status' => HabrSource::STATUS_PENDING]);

        $this->info("Reset {$count} failed source(s) to pending.");

        return self::SUCCESS;
    }
}
