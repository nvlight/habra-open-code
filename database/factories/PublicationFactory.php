<?php

namespace Database\Factories;

use App\Enums\Difficulty;
use App\Enums\PublicationLabel;
use App\Enums\PublicationStatus;
use App\Enums\PublicationType;
use App\Models\Company;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Publication>
 */
class PublicationFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'user_id' => User::factory(),
            'company_id' => null,
            'type' => PublicationType::Article,
            'status' => PublicationStatus::Published,
            'title' => ucfirst($title),
            'lead' => fake()->paragraph(),
            'body' => collect(range(1, 5))->map(fn () => fake()->paragraph())->implode("\n\n"),
            'cover' => null,
            'difficulty' => fake()->randomElement(Difficulty::cases()),
            'label' => fake()->randomElement(PublicationLabel::cases()),
            'is_translation' => false,
            'source_url' => null,
            'original_author' => null,
            'is_recovery_mode' => false,
            'reading_time' => fake()->numberBetween(1, 40),
            'views_count' => fake()->numberBetween(100, 100000),
            'reach' => fake()->numberBetween(500, 500000),
            'rating' => 0,
            'votes_up' => 0,
            'votes_down' => 0,
            'comments_count' => 0,
            'bookmarks_count' => 0,
            'published_at' => now()->subMinutes(fake()->numberBetween(10, 20000)),
        ];
    }

    public function post(): static
    {
        return $this->state(fn () => [
            'type' => PublicationType::Post,
            'difficulty' => null,
        ]);
    }

    public function news(): static
    {
        return $this->state(fn () => [
            'type' => PublicationType::News,
            'difficulty' => null,
            'label' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => PublicationStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function sandbox(): static
    {
        return $this->state(fn () => [
            'status' => PublicationStatus::Sandbox,
            'published_at' => null,
        ]);
    }

    public function translation(): static
    {
        return $this->state(fn () => [
            'is_translation' => true,
            'source_url' => 'https://example.com/'.fake()->slug(),
            'original_author' => fake()->name(),
        ]);
    }

    public function corporate(): static
    {
        return $this->state(fn () => [
            'company_id' => Company::factory(),
        ]);
    }
}
