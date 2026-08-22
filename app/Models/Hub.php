<?php

namespace App\Models;

use Database\Factories\HubFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string $name
 * @property string $alias
 * @property string|null $description
 * @property string|null $avatar
 * @property float $rating
 * @property int $subscribers_count
 */
#[Fillable(['name', 'alias', 'description', 'avatar'])]
class Hub extends Model
{
    /** @use HasFactory<HubFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
        ];
    }

    public function publications(): BelongsToMany
    {
        return $this->belongsToMany(Publication::class);
    }

    public function subscribers(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscribable');
    }
}
