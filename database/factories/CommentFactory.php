<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'publication_id' => Publication::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'body' => fake()->paragraph(),
            'rating' => 0,
        ];
    }

    public function reply(): static
    {
        return $this->state(fn () => [
            'parent_id' => Comment::factory(),
        ]);
    }
}
