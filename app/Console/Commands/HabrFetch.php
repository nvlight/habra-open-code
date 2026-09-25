<?php

namespace App\Console\Commands;

use App\Models\HabrSource;
use App\Services\HabrKekService;
use Illuminate\Console\Command;
use Throwable;

class HabrFetch extends Command
{
    protected $signature = 'habr:fetch {--limit=500 : Сколько статей обработать за один запуск}';

    protected $description = 'Fetch article bodies from habr kek API for pending habr_sources (sync run)';

    public function handle(HabrKekService $kekService): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $sources = HabrSource::query()->pending()->orderBy('source_id')->limit($limit)->get();

        if ($sources->isEmpty()) {
            $this->info('No pending sources.');

            return self::SUCCESS;
        }

        $fetched = 0;

        foreach ($sources as $source) {
            try {
                $kekService->fetch($source);
                $fetched++;
            } catch (Throwable $e) {
                $this->warn("Failed source {$source->source_id}: {$e->getMessage()}");

                if ($source->attempts >= 5) {
                    $source->forceFill(['status' => HabrSource::STATUS_FAILED])->save();
                }
            }

            usleep(400_000);
        }

        $this->info("Fetched {$fetched} of {$sources->count()} sources.");

        return self::SUCCESS;
    }
}
