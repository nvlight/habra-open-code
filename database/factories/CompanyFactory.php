<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).fake()->unique()->numberBetween(1, 999),
            'description' => fake()->paragraph(),
            'avatar' => null,
            'website' => 'https://'.fake()->domainName(),
            'rating' => fake()->randomFloat(2, 0, 2000),
            'subscribers_count' => 0,
            'location' => fake()->country(),
            'size' => fake()->randomElement(['11–50', '51–100', '101–200', '201–500', '501–1000']),
            'founded_at' => fake()->optional()->dateTimeBetween('-15 years', '-1 year'),
            'representative_id' => null,
        ];
    }
}
