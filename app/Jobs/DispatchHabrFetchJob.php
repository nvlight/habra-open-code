<?php

namespace App\Jobs;

use App\Models\HabrSource;
use App\Services\HabrCrawlState;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchHabrFetchJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    private const PER_BATCH = 2000;

    public function __construct(public ?int $cursor = null, public ?int $limit = null) {}

    public function handle(): void
    {
        $state = app(HabrCrawlState::class);

        if ($state->isCanceled()) {
            return;
        }

        if (($this->limit ?? 1) <= 0) {
            return;
        }

        $state->touch();

        $ids = HabrSource::query()
            ->when($this->cursor !== null, fn ($query) => $query->where('source_id', '>', $this->cursor))
            ->pending()
            ->orderBy('source_id')
            ->limit(self::PER_BATCH)
            ->pluck('source_id');

        if ($ids->isEmpty()) {
            $state->clearCursor();

            return;
        }

        foreach ($ids as $id) {
            FetchHabrArticleJob::dispatch((int) $id);
        }

        $state->setCursor((int) $ids->last());

        if ($ids->count() < self::PER_BATCH) {
            return;
        }

        static::dispatch(
            (int) $ids->last(),
            $this->limit === null ? null : max(0, $this->limit - $ids->count()),
        );
    }
}
