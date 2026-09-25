<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class HabrCrawlState
{
    private const KEY_CANCEL = 'habr:crawl:cancel';

    private const KEY_ACTIVITY = 'habr:crawl:last_activity';

    private const KEY_QUEUED_AT = 'habr:crawl:queued_at';

    private const KEY_CURSOR = 'habr:crawl:cursor';

    private const ACTIVITY_TTL_SECONDS = 900;

    private const RUNNING_WINDOW_SECONDS = 300;

    public function start(): void
    {
        Cache::forget(self::KEY_CANCEL);
        Cache::forget(self::KEY_CURSOR);
        Cache::put(self::KEY_QUEUED_AT, now()->getTimestamp());
    }

    public function stop(): void
    {
        Cache::put(self::KEY_CANCEL, true);
    }

    /**
     * @phpstan-impure
     */
    public function isCanceled(): bool
    {
        return (bool) Cache::get(self::KEY_CANCEL, false);
    }

    public function touch(): void
    {
        Cache::put(self::KEY_ACTIVITY, now()->getTimestamp(), self::ACTIVITY_TTL_SECONDS);
    }

    public function isRunning(): bool
    {
        $activity = Cache::get(self::KEY_ACTIVITY);

        return $activity !== null && (time() - (int) $activity) <= self::RUNNING_WINDOW_SECONDS;
    }

    public function lastActivity(): ?Carbon
    {
        $activity = Cache::get(self::KEY_ACTIVITY);

        return $activity !== null ? Carbon::createFromTimestamp((int) $activity) : null;
    }

    public function queuedAt(): ?Carbon
    {
        $queuedAt = Cache::get(self::KEY_QUEUED_AT);

        return $queuedAt !== null ? Carbon::createFromTimestamp((int) $queuedAt) : null;
    }

    public function cursor(): ?int
    {
        $cursor = Cache::get(self::KEY_CURSOR);

        return $cursor === null ? null : (int) $cursor;
    }

    public function setCursor(int $sourceId): void
    {
        Cache::put(self::KEY_CURSOR, $sourceId);
    }

    public function clearCursor(): void
    {
        Cache::forget(self::KEY_CURSOR);
    }

    /**
     * Best-effort purge of the pending RabbitMQ queue via the management API.
     * Fails silently when the API is unreachable; the cancel flag still drains the queue.
     */
    public function purgeQueue(): void
    {
        try {
            $host = (string) config('queue.connections.rabbitmq.hosts.0.host', '127.0.0.1');
            $user = (string) config('queue.connections.rabbitmq.hosts.0.user', 'guest');
            $password = (string) config('queue.connections.rabbitmq.hosts.0.password', 'guest');
            $queue = (string) config('queue.connections.rabbitmq.queue', 'default');

            $baseUrl = (string) config('queue.connections.rabbitmq.mgmt_url', "http://{$host}:15672");

            Http::withBasicAuth($user, $password)
                ->delete("{$baseUrl}/api/queues/%2F/{$queue}/contents")
                ->throw();
        } catch (\Throwable) {
            // ignore: the cancel flag alone must stop the crawl.
        }
    }
}
