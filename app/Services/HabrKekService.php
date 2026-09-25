<?php

namespace App\Services;

use App\Exceptions\HabrRateLimitedException;
use App\Models\HabrSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HabrKekService
{
    private const API_URL = 'https://habr.com/kek/v2/articles/';

    public function fetch(HabrSource $source): void
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Habra-Open-Code-Archive/1.0 (+contact via project repository)',
            'Accept' => 'application/json',
        ])
            ->acceptJson()
            ->timeout(20)
            ->get(self::API_URL.$source->source_id);

        $source->increment('attempts');
        $source->http_status = $response->status();
        $source->save();

        if ($response->notFound()) {
            $source->update([
                'status' => HabrSource::STATUS_EXCLUDED,
                'http_status' => 404,
            ]);

            return;
        }

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?: 30);

            throw new HabrRateLimitedException("Habr kek API rate limited for source {$source->source_id}", $retryAfter);
        }

        if ($response->serverError() || $response->status() >= 400) {
            throw new \RuntimeException("Habr kek API returned HTTP {$response->status()} for source {$source->source_id}");
        }

        $payload = $response->json();

        if (! is_array($payload) || $payload === []) {
            throw new \RuntimeException("Habr kek API returned non-JSON payload for source {$source->source_id}");
        }

        $this->storeContent($source, $response->body());

        $source->update([
            'status' => HabrSource::STATUS_FETCHED,
            'published_at' => data_get($payload, 'timePublished') ?: $this->fallbackPublishedAt($source),
            'title' => Str::limit(strip_tags((string) data_get($payload, 'titleHtml', '')), 255) ?: null,
            'http_status' => $response->status(),
            'fetched_at' => now(),
        ]);

        Log::info("Fetched habr source {$source->source_id} ({$source->type})");
    }

    private function storeContent(HabrSource $source, string $body): void
    {
        $path = "habr/{$source->source_id}.json";

        Storage::disk('local')->put($path, $body);

        $source->forceFill([
            'content_file' => $path,
            'content_hash' => hash('sha256', $body),
        ])->save();
    }

    private function fallbackPublishedAt(HabrSource $source): ?Carbon
    {
        return $source->lastmod ?: null;
    }
}
