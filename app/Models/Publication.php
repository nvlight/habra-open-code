<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\PublicationLabel;
use App\Enums\PublicationStatus;
use App\Enums\PublicationType;
use Database\Factories\PublicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $company_id
 * @property PublicationType $type
 * @property PublicationStatus $status
 * @property string $title
 * @property string|null $lead
 * @property string $body
 * @property string|null $cover
 * @property Difficulty|null $difficulty
 * @property PublicationLabel|null $label
 * @property bool $is_translation
 * @property string|null $source_url
 * @property string|null $original_author
 * @property bool $is_recovery_mode
 * @property int $reading_time
 * @property int $views_count
 * @property int $reach
 * @property int $rating
 * @property int $votes_up
 * @property int $votes_down
 * @property int $comments_count
 * @property int $bookmarks_count
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'company_id',
    'type',
    'status',
    'title',
    'lead',
    'body',
    'cover',
    'difficulty',
    'label',
    'is_translation',
    'source_url',
    'original_author',
    'is_recovery_mode',
    'reading_time',
    'reach',
    'published_at',
])]
class Publication extends Model
{
    /** @use HasFactory<PublicationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => PublicationType::class,
            'status' => PublicationStatus::class,
            'difficulty' => Difficulty::class,
            'label' => PublicationLabel::class,
            'is_translation' => 'boolean',
            'is_recovery_mode' => 'boolean',
            'published_at' => 'datetime',
            'rating' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function hubs(): BelongsToMany
    {
        return $this->belongsToMany(Hub::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function votes(): MorphMany
    {
        return $this->morphMany(Vote::class, 'voteable');
    }

    public function bookmarkedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bookmarks')->withTimestamps();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeType(Builder $query, PublicationType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PublicationStatus::Published->value)->orderByDesc('published_at');
    }
}
