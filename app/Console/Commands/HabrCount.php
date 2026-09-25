<?php

namespace App\Console\Commands;

use App\Models\HabrSource;
use App\Services\HabrSitemapService;
use Illuminate\Console\Command;

class HabrCount extends Command
{
    protected $signature = 'habr:count {--since= : Считать публикации не ранее этой даты (YYYY-MM-DD)}';

    protected $description = 'Count habr articles collected in habr_sources';

    public function handle(): int
    {
        $since = $this->option('since') ?: HabrSitemapService::DEFAULT_SINCE_DATE;

        $total = HabrSource::query()->count();
        $published = HabrSource::publishedSince($since)->count();

        $byStatus = HabrSource::query()
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $byType = HabrSource::publishedSince($since)
            ->selectRaw('type, count(*) as cnt')
            ->groupBy('type')
            ->pluck('cnt', 'type')
            ->all();

        $this->info("Всего записей: {$total}");
        $this->info("Опубликовано с {$since}: {$published}");
        $this->newLine();
        $this->table(['По типу', 'Кол-во'], array_map(
            static fn (string $type, int $cnt) => [$type, $cnt],
            array_keys($byType),
            array_values($byType),
        ));
        $this->table(['По статусу', 'Кол-во'], array_map(
            static fn (string $status, int $cnt) => [$status, $cnt],
            array_keys($byStatus),
            array_values($byStatus),
        ));

        return self::SUCCESS;
    }
}
