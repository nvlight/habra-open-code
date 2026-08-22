<?php

use App\Models\Comment;
use App\Models\Hub;
use App\Models\Publication;
use App\Models\User;

it('shows only publications of subscribed hubs in the personal feed', function () {
    $user = User::factory()->create();
    $python = Hub::factory()->create(['alias' => 'python']);
    $golang = Hub::factory()->create(['alias' => 'go']);

    $inFeed = Publication::factory()->published()->create();
    $inFeed->hubs()->sync([$python->id]);

    $outOfFeed = Publication::factory()->published()->create();
    $outOfFeed->hubs()->sync([$golang->id]);

    $user->subscriptions()->create([
        'subscribable_type' => Hub::class,
        'subscribable_id' => $python->id,
    ]);

    $data = $this->actingAs($user, 'sanctum')
        ->getJson('/api/feed')
        ->assertOk()
        ->json('data');

    expect(collect($data)->pluck('id')->all())->toEqual([$inFeed->id]);
});

it('falls back to the global feed without subscriptions', function () {
    Publication::factory()->count(2)->published()->create();

    $data = $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/feed')
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(2);
});

it('applies min_rating from feed settings stored on profile', function () {
    $user = User::factory()->create(['feed_settings' => ['min_rating' => 50]]);

    $hot = Publication::factory()->published()->create();
    $hot->forceFill(['rating' => 60])->save();

    Publication::factory()->published()->create();

    $data = $this->actingAs($user, 'sanctum')->getJson('/api/feed')->json('data');

    expect($data)->toHaveCount(1)
        ->and((int) $data[0]['rating'])->toBeGreaterThanOrEqual(50);
});

it('requires authentication for the feed', function () {
    $this->getJson('/api/feed')->assertUnauthorized();
});

it('lists authors sorted by rating', function () {
    $weak = User::factory()->create();
    $weak->forceFill(['rating' => 5])->save();
    $top = User::factory()->create();
    $top->forceFill(['rating' => 500])->save();

    $data = $this->getJson('/api/users')->assertOk()->json('data');

    expect($data)->toHaveCount(2)
        ->and($data[0]['login'])->toBe($top->login);
});

it('lists comments of a user', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();

    Comment::factory()->for($author, 'author')->count(2)->create();
    Comment::factory()->for($other, 'author')->create();

    $data = $this->getJson("/api/users/{$author->login}/comments")->assertOk()->json('data');

    expect($data)->toHaveCount(2)
        ->and(collect($data)->every(fn (array $c) => $c['author']['login'] === $author->login))->toBeTrue();
});

it('lists users the profile follows', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();

    $user->subscriptions()->create([
        'subscribable_type' => Hub::class,
        'subscribable_id' => Hub::factory()->create()->id,
    ]);
    $user->subscriptions()->create([
        'subscribable_type' => User::class,
        'subscribable_id' => $friend->id,
    ]);

    $data = $this->getJson("/api/users/{$user->login}/following")->assertOk()->json('data');

    expect(collect($data)->pluck('login')->all())->toEqual([$friend->login]);
});
