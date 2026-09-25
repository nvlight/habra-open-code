<?php

namespace App\Console\Commands;

use App\Services\HabrSitemapService;
use Illuminate\Console\Command;

class HabrDiscover extends Command
{
    protected $signature = 'habr:discover {--since= : Собирать URL с публикациями не ранее этой даты (YYYY-MM-DD)}';

    protected $description = 'Discover habr.com article URLs from public sitemaps and store them in habr_sources';

    public function handle(HabrSitemapService $sitemapService): int
    {
        $since = $this->option('since') ?: HabrSitemapService::DEFAULT_SINCE_DATE;

        $this->info("Discovering habr sitemaps since {$since}...");

        $stats = $sitemapService->discover($since);

        $this->info("Done: created {$stats['created']}, updated {$stats['updated']}.");

        return self::SUCCESS;
    }
}
