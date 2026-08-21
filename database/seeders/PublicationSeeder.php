<?php

namespace Database\Seeders;

use App\Enums\PublicationStatus;
use App\Models\Company;
use App\Models\Hub;
use App\Models\Publication;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class PublicationSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->whereNull('company_id')->get();
        $hubs = Hub::query()->pluck('id');
        $companies = Company::query()->with('representative')->get();

        foreach (range(1, 60) as $i) {
            $type = fake()->randomElement(['article', 'article', 'article', 'post', 'news']);
            $isCorporate = $type !== 'post' && fake()->boolean(30);
            $status = fake()->randomElement([
                PublicationStatus::Published, PublicationStatus::Published,
                PublicationStatus::Published, PublicationStatus::Sandbox, PublicationStatus::Draft,
            ]);

            /** @var User $author */
            if ($isCorporate) {
                /** @var Company $company */
                $company = $companies->random();
                $author = $company->representative ?? User::factory()->create();
                $companyId = $company->id;
            } else {
                $author = $users->random();
                $companyId = null;
            }

            /** @var Publication $publication */
            $publication = Publication::query()->create([
                'user_id' => $author->id,
                'company_id' => $companyId,
                'type' => $type,
                'status' => $status,
                'title' => ucfirst(fake()->unique()->sentence(fake()->numberBetween(4, 9))),
                'lead' => fake()->paragraph(),
                'body' => collect(range(1, fake()->numberBetween(3, 7)))
                    ->map(fn () => '<p>'.fake()->paragraph().'</p>')
                    ->implode("\n"),
                'difficulty' => $type === 'article' ? fake()->randomElement(['easy', 'medium', 'hard']) : null,
                'label' => $type === 'article'
                    ? fake()->randomElement(['tutorial', 'case', 'analytics', 'opinion', 'review', 'digest'])
                    : null,
                'is_translation' => fake()->boolean(20),
                'source_url' => null,
                'original_author' => null,
                'reading_time' => fake()->numberBetween(1, 35),
                'views_count' => fake()->numberBetween(50, 120000),
                'reach' => fake()->numberBetween(200, 300000),
                'rating' => 0,
                'published_at' => $status === PublicationStatus::Published
                    ? now()->subMinutes(fake()->numberBetween(5, 40000))
                    : null,
            ]);

            if ($status === PublicationStatus::Sandbox) {
                $publication->update(['published_at' => null]);
            }

            $publication->hubs()->sync($hubs->random(fake()->numberBetween(1, 4)));

            $tags = collect(range(1, fake()->numberBetween(2, 6)))
                ->map(fn () => Tag::query()->firstOrCreate(['name' => fake()->word()]))
                ->pluck('id');

            $publication->tags()->sync($tags);
        }
    }
}
