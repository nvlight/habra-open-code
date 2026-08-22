<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $login
 * @property string|null $about
 * @property string|null $avatar
 * @property int $karma
 * @property float $rating
 * @property string|null $location
 * @property int|null $company_id
 * @property int|null $invited_by
 */
#[Fillable([
    'name',
    'login',
    'about',
    'avatar',
    'location',
    'company_id',
    'invited_by',
    'feed_settings',
    'email',
    'password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'feed_settings' => 'array',
            'karma' => 'integer',
            'rating' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'invited_by');
    }

    public function invites(): HasMany
    {
        return $this->hasMany(self::class, 'invited_by');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'badge_user')
            ->withPivot('awarded_at')
            ->withTimestamps();
    }

    public function bookmarkedPublications(): BelongsToMany
    {
        return $this->belongsToMany(Publication::class, 'bookmarks')->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscribers(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscribable');
    }
}
