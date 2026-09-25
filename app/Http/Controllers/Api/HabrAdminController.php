<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HabrSourceResource;
use App\Jobs\DiscoverHabrUrlsJob;
use App\Jobs\DispatchHabrFetchJob;
use App\Models\HabrSource;
use App\Services\HabrCrawlState;
use App\Services\HabrSitemapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HabrAdminController extends Controller
{
    public function discover(): JsonResponse
    {
        app(HabrCrawlState::class)->start();

        DiscoverHabrUrlsJob::dispatch();

        return response()->json([
            'message' => 'Сбор URL из sitemap поставлен в очередь',
            'queued' => true,
        ], 202);
    }

    public function fetch(Request $request): JsonResponse
    {
        app(HabrCrawlState::class)->start();

        $limit = max(1, $request->integer('limit', 5000));

        DispatchHabrFetchJob::dispatch(limit: $limit);

        return response()->json([
            'message' => 'Загрузка контента поставлена в очередь',
            'queued' => true,
            'limit' => $limit,
        ], 202);
    }

    public function stop(): JsonResponse
    {
        $state = app(HabrCrawlState::class);

        $state->stop();
        $state->purgeQueue();

        return response()->json([
            'message' => 'Остановка парсинга',
            'cancel_requested' => true,
        ], 202);
    }

    public function stats(Request $request): JsonResponse
    {
        $since = $request->string('since', HabrSitemapService::DEFAULT_SINCE_DATE)->toString();

        $state = app(HabrCrawlState::class);

        $total = HabrSource::query()->count();

        $byStatus = HabrSource::query()
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $byType = HabrSource::query()
            ->selectRaw('type, count(*) as cnt')
            ->groupBy('type')
            ->pluck('cnt', 'type')
            ->all();

        $publishedSince = HabrSource::publishedSince($since)->count();

        return response()->json([
            'total' => $total,
            'since' => $since,
            'published_since' => $publishedSince,
            'pending' => (int) ($byStatus[HabrSource::STATUS_PENDING] ?? 0),
            'fetched' => (int) ($byStatus[HabrSource::STATUS_FETCHED] ?? 0),
            'failed' => (int) ($byStatus[HabrSource::STATUS_FAILED] ?? 0),
            'excluded' => (int) ($byStatus[HabrSource::STATUS_EXCLUDED] ?? 0),
            'by_status' => $byStatus,
            'by_type' => $byType,
            'is_crawling' => $state->isRunning(),
            'cancel_requested' => $state->isCanceled(),
            'queued_at' => $state->queuedAt()?->toIso8601String(),
            'last_activity' => $state->lastActivity()?->toIso8601String(),
        ]);
    }

    public function urls(Request $request): AnonymousResourceCollection
    {
        $perPage = min(100, max(1, $request->integer('per_page', 25)));

        $query = HabrSource::query()->orderByDesc('source_id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('since')) {
            $query->publishedSince($request->string('since')->toString());
        }

        return HabrSourceResource::collection($query->paginate($perPage));
    }
}
