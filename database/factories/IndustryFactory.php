<?php

namespace Database\Factories;

use App\Models\Industry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Industry>
 */
class IndustryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => mb_convert_case($name, MB_CASE_TITLE, 'UTF-8'),
            'slug' => Str::slug($name),
        ];
    }
}
