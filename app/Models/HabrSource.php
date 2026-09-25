<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $source_id
 * @property string $url
 * @property string $type
 * @property string|null $company_slug
 * @property string|null $title
 * @property Carbon|null $published_at
 * @property Carbon|null $lastmod
 * @property string $status
 * @property int $attempts
 * @property int|null $http_status
 * @property string|null $content_file
 * @property string|null $content_hash
 * @property Carbon|null $fetched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'source_id',
    'url',
    'type',
    'company_slug',
    'title',
    'published_at',
    'lastmod',
    'status',
    'attempts',
    'http_status',
    'content_file',
    'content_hash',
    'fetched_at',
])]
class HabrSource extends Model
{
    public const TYPE_ARTICLE = 'article';

    public const TYPE_NEWS = 'news';

    public const TYPE_POST = 'post';

    public const TYPE_SPECIAL = 'special';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FETCHED = 'fetched';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXCLUDED = 'excluded';

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'lastmod' => 'datetime',
            'fetched_at' => 'datetime',
            'attempts' => 'integer',
            'http_status' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublishedSince(Builder $query, string $date): Builder
    {
        return $query->where('published_at', '>=', $date);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
