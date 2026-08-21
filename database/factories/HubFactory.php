<?php

namespace Database\Factories;

use App\Models\Hub;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Hub>
 */
class HubFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'alias' => Str::slug($name),
            'description' => fake()->sentence(),
            'avatar' => null,
            'rating' => fake()->randomFloat(2, 0, 1500),
            'subscribers_count' => 0,
        ];
    }
}
