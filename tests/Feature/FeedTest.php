<?php

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
