<?php

namespace App\Http\Resources;

use App\Models\HabrSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HabrSource
 */
class HabrSourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'source_id' => $this->source_id,
            'url' => $this->url,
            'type' => $this->type,
            'company_slug' => $this->company_slug,
            'title' => $this->title,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'http_status' => $this->http_status,
            'published_at' => $this->published_at?->toIso8601String(),
            'lastmod' => $this->lastmod?->toIso8601String(),
            'content_file' => $this->content_file,
            'fetched_at' => $this->fetched_at?->toIso8601String(),
        ];
    }
}
