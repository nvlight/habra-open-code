<?php

namespace App\Jobs;

use App\Exceptions\HabrRateLimitedException;
use App\Models\HabrSource;
use App\Services\HabrCrawlState;
use App\Services\HabrKekService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchHabrArticleJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    /** @var array<int> */
    public array $backoff = [10, 30, 120];

    public function __construct(public int $sourceId) {}

    public function handle(HabrKekService $kekService): void
    {
        $state = app(HabrCrawlState::class);

        if ($state->isCanceled()) {
            return;
        }

        /** @var HabrSource|null $source */
        $source = HabrSource::query()->where('source_id', $this->sourceId)->first();

        if ($source === null || $source->status !== HabrSource::STATUS_PENDING) {
            return;
        }

        try {
            $kekService->fetch($source);
        } catch (HabrRateLimitedException $e) {
            if (! $state->isCanceled()) {
                self::dispatch($this->sourceId)->delay(max($e->retryAfter, 5));
            }

            return;
        } catch (Throwable $e) {
            Log::warning("Habr fetch failed for source {$this->sourceId}: {$e->getMessage()}");

            throw $e;
        } finally {
            $state->touch();
        }
    }

    public function failed(?Throwable $e): void
    {
        /** @var HabrSource|null $source */
        $source = HabrSource::query()->where('source_id', $this->sourceId)->first();

        if ($source !== null && $source->status === HabrSource::STATUS_PENDING) {
            $source->forceFill(['status' => HabrSource::STATUS_FAILED])->save();
        }
    }
}
