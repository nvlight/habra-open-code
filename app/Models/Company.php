<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $avatar
 * @property string|null $website
 * @property float $rating
 * @property int $subscribers_count
 * @property string|null $location
 * @property string|null $size
 * @property Carbon|null $founded_at
 * @property int|null $representative_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $employees_count
 */
#[Fillable([
    'name',
    'slug',
    'description',
    'avatar',
    'website',
    'location',
    'size',
    'founded_at',
    'representative_id',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'founded_at' => 'date',
            'rating' => 'decimal:2',
        ];
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role');
    }

    public function industries(): BelongsToMany
    {
        return $this->belongsToMany(Industry::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function subscribers(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscribable');
    }
}
