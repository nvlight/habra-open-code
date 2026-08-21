<?php

namespace Database\Seeders;

use App\Enums\VoteSubject;
use App\Models\Badge;
use App\Models\Comment;
use App\Models\Company;
use App\Models\Hub;
use App\Models\Publication;
use App\Models\User;
use App\Services\VoteService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class InteractionSeeder extends Seeder
{
    public function __construct(
        private readonly VoteService $votes,
    ) {}

    public function run(): void
    {
        $users = User::query()->get();
        /** @var Collection<int, Publication> $publications */
        $publications = Publication::query()->where('status', 'published')->with('comments')->get();

        foreach ($publications as $publication) {
            foreach (range(1, fake()->numberBetween(0, 8)) as $_) {
                /** @var Comment|null $top */
                $top = Comment::query()->create([
                    'publication_id' => $publication->id,
                    'user_id' => $users->random()->id,
                    'body' => fake()->paragraph(fake()->numberBetween(1, 3)),
                ]);

                foreach (range(1, fake()->numberBetween(0, 3)) as $__ => $depth) {
                    if (! fake()->boolean(60)) {
                        break;
                    }

                    /** @var Comment $reply */
                    $reply = Comment::query()->create([
                        'publication_id' => $publication->id,
                        'user_id' => $users->random()->id,
                        'parent_id' => $top->id,
                        'body' => fake()->sentence(fake()->numberBetween(5, 15)),
                    ]);
                }
            }

            $publication->update([
                'comments_count' => $publication->comments()->count(),
            ]);
        }

        foreach ($publications as $publication) {
            foreach (range(1, fake()->numberBetween(0, 25)) as $_) {
                $this->votes->vote(
                    $users->random(),
                    VoteSubject::Publication,
                    $publication->id,
                    fake()->boolean(75) ? 1 : -1,
                );
            }
        }

        foreach (Comment::query()->get() as $comment) {
            foreach (range(1, fake()->numberBetween(0, 6)) as $_) {
                $this->votes->vote(
                    $users->random(),
                    VoteSubject::Comment,
                    $comment->id,
                    fake()->boolean(70) ? 1 : -1,
                );
            }
        }

        foreach (User::query()->get() as $user) {
            foreach (range(1, fake()->numberBetween(0, 10)) as $_) {
                $voter = $users->where('id', '!=', $user->id)->random();

                $this->votes->vote($voter, VoteSubject::User, $user->id, fake()->boolean(80) ? 1 : -1);
            }
        }

        foreach ($users as $user) {
            $bookmarks = $publications->random(fake()->numberBetween(0, 8));

            foreach ($bookmarks as $publication) {
                $attached = $user->bookmarkedPublications()->syncWithoutDetaching([$publication->id]);

                if ($attached !== []) {
                    $publication->increment('bookmarks_count');
                }
            }
        }

        foreach ($users as $user) {
            foreach (Hub::query()->inRandomOrder()->limit(fake()->numberBetween(2, 10))->pluck('id') as $hubId) {
                $subscribed = $user->subscriptions()->firstOrCreate([
                    'subscribable_type' => Hub::class,
                    'subscribable_id' => $hubId,
                ]);

                if ($subscribed->wasRecentlyCreated) {
                    Hub::query()->whereKey($hubId)->increment('subscribers_count');
                }
            }

            foreach (Company::query()->inRandomOrder()->limit(fake()->numberBetween(0, 4))->pluck('id') as $companyId) {
                $subscribed = $user->subscriptions()->firstOrCreate([
                    'subscribable_type' => Company::class,
                    'subscribable_id' => $companyId,
                ]);

                if ($subscribed->wasRecentlyCreated) {
                    Company::query()->whereKey($companyId)->increment('subscribers_count');
                }
            }

            foreach (User::query()->inRandomOrder()->limit(fake()->numberBetween(0, 5))->whereNot('id', $user->id)->pluck('id') as $authorId) {
                $user->subscriptions()->firstOrCreate([
                    'subscribable_type' => User::class,
                    'subscribable_id' => $authorId,
                ]);
            }
        }

        Badge::query()->get()
            ->each(fn (Badge $badge): bool => $badge->users()->syncWithoutDetaching(
                $users->random(fake()->numberBetween(1, 8))->pluck('id')->all()
            ) !== []);
    }
}
