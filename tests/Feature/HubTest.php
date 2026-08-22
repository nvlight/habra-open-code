<?php

use App\Models\Hub;
use App\Models\Publication;

it('lists hubs sorted by rating', function () {
    $weak = Hub::factory()->create();
    $weak->forceFill(['rating' => 10])->save();
    $strong = Hub::factory()->create();
    $strong->forceFill(['rating' => 900])->save();

    $data = $this->getJson('/api/hubs')->assertOk()->json('data');

    expect($data)->toHaveCount(2)
        ->and($data[0]['rating'] >= $data[1]['rating'])->toBeTrue()
        ->and($data[0]['alias'])->toBe($strong->alias);
});

it('shows a hub by alias', function () {
    $hub = Hub::factory()->create(['alias' => 'python', 'subscribers_count' => 7]);

    $this->getJson('/api/hubs/python')
        ->assertOk()
        ->assertJsonPath('data.alias', 'python')
        ->assertJsonPath('data.subscribers_count', 7);
});

it('returns 404 for unknown hub', function () {
    $this->getJson('/api/hubs/no_such_hub')->assertNotFound();
});

it('lists published publications of a hub', function () {
    $hub = Hub::factory()->create();

    $inside = Publication::factory()->published()->create();
    $inside->hubs()->sync([$hub->id]);
    Publication::factory()->published()->create();
    Publication::factory()->sandbox()->create()->hubs()->sync([$hub->id]);

    $data = $this->getJson("/api/hubs/{$hub->alias}/publications")->assertOk()->json('data');

    expect(collect($data)->pluck('id')->all())->toEqual([$inside->id]);
});

it('filters hub publications by difficulty and minimum rating', function () {
    $hub = Hub::factory()->create();

    $match = Publication::factory()->published()->create(['difficulty' => 'hard']);
    $match->forceFill(['rating' => 30])->save();
    $match->hubs()->sync([$hub->id]);

    $easy = Publication::factory()->published()->create(['difficulty' => 'easy']);
    $easy->hubs()->sync([$hub->id]);

    $lowRated = Publication::factory()->published()->create(['difficulty' => 'hard']);
    $lowRated->forceFill(['rating' => 1])->save();
    $lowRated->hubs()->sync([$hub->id]);

    $data = $this->getJson("/api/hubs/{$hub->alias}/publications?difficulty=hard&min_rating=25")
        ->assertOk()
        ->json('data');

    expect(collect($data)->pluck('id')->all())->toEqual([$match->id]);
});
