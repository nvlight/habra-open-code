<?php

namespace Database\Factories;

use App\Models\Publication;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vote>
 */
class VoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'voteable_type' => Publication::class,
            'voteable_id' => 1,
            'value' => fake()->randomElement([1, -1]),
        ];
    }
}
