<?php

namespace App\Jobs;

use App\Services\HabrCrawlState;
use App\Services\HabrSitemapService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DiscoverHabrUrlsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 1800;

    public function __construct(public ?string $sinceDate = null) {}

    public function handle(HabrSitemapService $sitemapService): void
    {
        $state = app(HabrCrawlState::class);

        if ($state->isCanceled()) {
            return;
        }

        $state->start();
        $state->touch();

        $sitemapService->discover($this->sinceDate ?? HabrSitemapService::DEFAULT_SINCE_DATE);
    }
}
