<?php

use App\Exceptions\HabrRateLimitedException;
use App\Jobs\DiscoverHabrUrlsJob;
use App\Jobs\DispatchHabrFetchJob;
use App\Jobs\FetchHabrArticleJob;
use App\Models\HabrSource;
use App\Models\User;
use App\Services\HabrCrawlState;
use App\Services\HabrKekService;
use App\Services\HabrSitemapService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Cache::forget('habr:crawl:cancel');
    Cache::forget('habr:crawl:last_activity');
    Cache::forget('habr:crawl:queued_at');
});

it('parses habr article urls and normalizes types', function (string $url, ?array $expected) {
    $service = app(HabrSitemapService::class);

    expect($service->parseUrl($url))->toBe($expected);
})->with([
    'article' => ['https://habr.com/ru/articles/1085922/', [
        'source_id' => 1085922,
        'url' => 'https://habr.com/ru/articles/1085922/',
        'type' => HabrSource::TYPE_ARTICLE,
        'company_slug' => null,
    ]],
    'company news' => ['https://habr.com/ru/companies/koda/news/1085908/', [
        'source_id' => 1085908,
        'url' => 'https://habr.com/ru/companies/koda/news/1085908/',
        'type' => HabrSource::TYPE_NEWS,
        'company_slug' => 'koda',
    ]],
    'post' => ['https://habr.com/ru/posts/1085900/', [
        'source_id' => 1085900,
        'url' => 'https://habr.com/ru/posts/1085900/',
        'type' => HabrSource::TYPE_POST,
        'company_slug' => null,
    ]],
    'special' => ['https://habr.com/ru/specials/1080998/', [
        'source_id' => 1080998,
        'url' => 'https://habr.com/ru/specials/1080998/',
        'type' => HabrSource::TYPE_SPECIAL,
        'company_slug' => null,
    ]],
    'english is ignored' => ['https://habr.com/en/articles/1085922/', null],
    'home page is ignored' => ['https://habr.com/ru/', null],
]);

it('discovers urls from sitemaps and stores only recent unique entries', function () {
    Http::fake([
        'https://habr.com/sitemap.xml' => Http::response(
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<sitemapindex><sitemap><loc>https://habr.com/sitemap_articles1.xml</loc></sitemap>'
            .'<sitemap><loc>https://habr.com/sitemap_hubs.xml</loc></sitemap></sitemapindex>'
        ),
        'https://habr.com/sitemap_articles1.xml' => Http::response(
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset>'
            .'<url><loc>https://habr.com/ru/articles/1085922/</loc>'
            .'<priority>1</priority><lastmod>2026-09-24T00:35:13+00:00</lastmod></url>'
            .'<url><loc>https://habr.com/ru/companies/koda/news/1085908/</loc>'
            .'<priority>1</priority><lastmod>2026-09-23T20:00:00+00:00</lastmod></url>'
            .'<url><loc>https://habr.com/ru/articles/982100/</loc>'
            .'<priority>1</priority><lastmod>2025-12-31T13:01:22+00:00</lastmod></url>'
            .'<url><loc>https://habr.com/en/articles/1085336/</loc>'
            .'<priority>1</priority><lastmod>2026-09-24T01:00:00+00:00</lastmod></url>'
            .'<url><loc>https://habr.com/ru/articles/899999/</loc>'
            .'<priority>1</priority><lastmod>2026-07-01T00:00:00+00:00</lastmod></url>'
            .'</urlset>'
        ),
    ]);

    $this->artisan('habr:discover')->assertExitCode(0);

    expect(HabrSource::count())->toBe(2);

    $article = HabrSource::query()->where('source_id', 1085922)->first();
    expect($article->type)->toBe(HabrSource::TYPE_ARTICLE)
        ->and($article->status)->toBe(HabrSource::STATUS_PENDING)
        ->and($article->lastmod->toIso8601String())->toBe('2026-09-24T00:35:13+00:00');

    $news = HabrSource::query()->where('source_id', 1085908)->first();
    expect($news->type)->toBe(HabrSource::TYPE_NEWS)
        ->and($news->company_slug)->toBe('koda');
});

it('fetches article content from kek api and stores the raw json', function () {
    Storage::fake('local');
    Http::fake([
        'https://habr.com/kek/v2/articles/1000000' => Http::response(json_encode([
            'id' => '1000000',
            'timePublished' => '2026-02-24T06:59:51+00:00',
            'titleHtml' => 'Технологии в основе VK Видео',
            'postType' => 'article',
        ])),
    ]);

    $source = HabrSource::query()->create([
        'source_id' => 1000000,
        'url' => 'https://habr.com/ru/articles/1000000/',
        'type' => HabrSource::TYPE_ARTICLE,
        'status' => HabrSource::STATUS_PENDING,
    ]);

    app(HabrKekService::class)->fetch($source);

    $source->refresh();

    expect($source->status)->toBe(HabrSource::STATUS_FETCHED)
        ->and($source->published_at->toDateString())->toBe('2026-02-24')
        ->and($source->title)->toBe('Технологии в основе VK Видео')
        ->and($source->attempts)->toBe(1)
        ->and($source->content_file)->toBe('habr/1000000.json')
        ->and(Storage::disk('local')->exists($source->content_file))->toBeTrue()
        ->and($source->content_hash)->toBe(hash('sha256', json_encode([
            'id' => '1000000',
            'timePublished' => '2026-02-24T06:59:51+00:00',
            'titleHtml' => 'Технологии в основе VK Видео',
            'postType' => 'article',
        ])));
});

it('marks deleted sources as excluded on 404', function () {
    Http::fake([
        'https://habr.com/kek/v2/articles/980000' => Http::response('not found', 404),
    ]);

    $source = HabrSource::query()->create([
        'source_id' => 980000,
        'url' => 'https://habr.com/ru/articles/980000/',
        'type' => HabrSource::TYPE_ARTICLE,
        'status' => HabrSource::STATUS_PENDING,
    ]);

    app(HabrKekService::class)->fetch($source);

    expect($source->fresh()->status)->toBe(HabrSource::STATUS_EXCLUDED)
        ->and($source->fresh()->http_status)->toBe(404);
});

it('filled published_at rows are counted via publishedSince scope', function () {
    HabrSource::query()->create([
        'source_id' => 1085922,
        'url' => 'https://habr.com/ru/articles/1085922/',
        'type' => HabrSource::TYPE_ARTICLE,
        'status' => HabrSource::STATUS_FETCHED,
        'published_at' => '2026-02-24T06:59:51+00:00',
    ]);
    HabrSource::query()->create([
        'source_id' => 982000,
        'url' => 'https://habr.com/ru/articles/982000/',
        'type' => HabrSource::TYPE_ARTICLE,
        'status' => HabrSource::STATUS_FETCHED,
        'published_at' => '2025-12-31T13:01:22+00:00',
    ]);

    expect(HabrSource::publishedSince('2026-01-01')->count())->toBe(1);
});

it('admin can trigger discovery and fetch via queue and gets stats', function () {
    Queue::fake();
    Http::fake();

    $admin = User::query()->create([
        'name' => 'Admin',
        'login' => 'habr_admin',
        'email' => 'habr_admin@test.dev',
        'password' => 'password',
        'is_admin' => true,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/habr/discover')
        ->assertStatus(202);
    Queue::assertPushed(DiscoverHabrUrlsJob::class);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/habr/fetch', ['limit' => 100])
        ->assertStatus(202);
    Queue::assertPushed(DispatchHabrFetchJob::class, fn (DispatchHabrFetchJob $job) => $job->limit === 100);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/habr/stats')
        ->assertOk()
        ->assertJsonPath('total', 0)
        ->assertJsonPath('published_since', 0);
});

it('blocks non-admin users from admin endpoints', function () {
    Queue::fake();

    $user = User::query()->create([
        'name' => 'Обычный',
        'login' => 'plain_user',
        'email' => 'plain@test.dev',
        'password' => 'password',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/habr/discover')
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('dispatch job enqueues fetching for each pending source', function () {
    Queue::fake();

    foreach (range(1, 3) as $i) {
        HabrSource::query()->create([
            'source_id' => 1000000 + $i,
            'url' => 'https://habr.com/ru/articles/'.(1000000 + $i).'/',
            'type' => HabrSource::TYPE_ARTICLE,
            'status' => HabrSource::STATUS_PENDING,
        ]);
    }

    app(DispatchHabrFetchJob::class)->handle();

    Queue::assertPushed(FetchHabrArticleJob::class, 3);
    Queue::assertNotPushed(DispatchHabrFetchJob::class);
});

it('fetch job resolves the source by external source_id and fetches it', function () {
    Queue::fake();

    $source = HabrSource::query()->create([
        'source_id' => 1085922,
        'url' => 'https://habr.com/ru/articles/1085922/',
        'type' => HabrSource::TYPE_ARTICLE,
        'status' => HabrSource::STATUS_PENDING,
    ]);

    Storage::fake('local');
    Http::fake([
        'habr.com/*' => Http::response(['timePublished' => '2026-09-01T10:00:00Z',
            'postType' => 'article', 'titleHtml' => 'Article', 'textHtml' => '<p>body</p>'], 200),
    ]);

    app()->call([new FetchHabrArticleJob(1085922), 'handle']);

    $source->refresh();
    expect($source->status)->toBe(HabrSource::STATUS_FETCHED);
    expect($source->published_at->toDateString())->toBe('2026-09-01');
    Storage::disk('local')->assertExists('habr/1085922.json');
});

it('fetch job skips non-pending sources', function () {
    Queue::fake();

    $source = HabrSource::query()->create([
        'source_id' => 1085923,
        'url' => 'https://habr.com/ru/articles/1085923/',
        'type' => HabrSource::TYPE_ARTICLE,
        'status' => HabrSource::STATUS_PENDING,
    ]);

    Storage::fake('local');
    Http::fake();
    $source->forceFill(['status' => HabrSource::STATUS_FAILED])->save();

    app()->call([new FetchHabrArticleJob(1085923), 'handle']);

    $source->refresh();
    expect($source->status)->toBe(HabrSource::STATUS_FAILED);
    Storage::disk('local')->assertMissing('habr/1085923.json');
});

it('throws a rate-limit exception on 429 without writing content', function () {
    Storage::fake('local');
    Http::fake([
        'https://habr.com/kek/v2/articles/900100' => Http::response('too many', 429, ['Retry-After' => '15']),
    ]);

    $source = HabrSource::query()->create([
        'source_id' => 900100,
        'url' => 'https://habr.com/ru/news/900100/',
        'type' => HabrSource::TYPE_NEWS,
        'status' => HabrSource::STATUS_PENDING,
    ]);

    $retryAfter = null;

    try {
        app(HabrKekService::class)->fetch($source);
        $this->fail('Expected HabrRateLimitedException was not thrown.');
    } catch (HabrRateLimitedException $e) {
        $retryAfter = $e->retryAfter;
    }

    expect($retryAfter)->toBe(15)
        ->and($source->fresh()->status)->toBe(HabrSource::STATUS_PENDING)
        ->and($source->fresh()->http_status)->toBe(429)
        ->and($source->fresh()->attempts)->toBe(1);

    Storage::disk('local')->assertMissing('habr/900100.json');
});

it('re-dispatches the job with delay on 429 and keeps the source pending', function () {
    Queue::fake();
    Http::fake([
        'habr.com/*' => Http::response('too many', 429, ['Retry-After' => '15']),
    ]);

    HabrSource::query()->create([
        'source_id' => 900101,
        'url' => 'https://habr.com/ru/news/900101/',
        'type' => HabrSource::TYPE_NEWS,
        'status' => HabrSource::STATUS_PENDING,
    ]);

    app()->call([new FetchHabrArticleJob(900101), 'handle']);

    Queue::assertPushed(FetchHabrArticleJob::class, fn (FetchHabrArticleJob $job) => $job->sourceId === 900101);

    expect(HabrSource::query()->where('source_id', 900101)->first()->status)->toBe(HabrSource::STATUS_PENDING);

    Queue::assertNotPushed(DispatchHabrFetchJob::class);
});

it('dispatches the crawl in cursor batches and drains the pending tail', function () {
    Queue::fake();

    foreach (range(1, 2500) as $i) {
        HabrSource::query()->create([
            'source_id' => 90000000 + $i,
            'url' => 'https://habr.com/ru/articles/'.(90000000 + $i).'/',
            'type' => HabrSource::TYPE_ARTICLE,
            'status' => HabrSource::STATUS_PENDING,
        ]);
    }

    (new DispatchHabrFetchJob(limit: 2000))->handle();

    Queue::assertPushed(FetchHabrArticleJob::class, 2000);
    Queue::assertPushed(DispatchHabrFetchJob::class, 1);

    (new DispatchHabrFetchJob(cursor: 90000000 + 2000, limit: 500))->handle();

    Queue::assertPushed(FetchHabrArticleJob::class, 2500);
    Queue::assertPushed(DispatchHabrFetchJob::class, 1);
});

it('cascade stops enqueuing once the limit is exhausted', function () {
    Queue::fake();

    HabrSource::query()->create([
        'source_id' => 90000100,
        'url' => 'https://habr.com/ru/articles/90000100/',
        'type' => HabrSource::TYPE_ARTICLE,
        'status' => HabrSource::STATUS_PENDING,
    ]);

    (new DispatchHabrFetchJob(limit: 2000))->handle();

    Queue::assertPushed(FetchHabrArticleJob::class, 1);

    (new DispatchHabrFetchJob(cursor: 90000100, limit: 0))->handle();

    Queue::assertPushed(FetchHabrArticleJob::class, 1);
    Queue::assertPushed(DispatchHabrFetchJob::class, 0);
});

it('resumes from the cursor after the pending set shrinks below it (crawl stall regression)', function () {
    Queue::fake();

    foreach (range(1, 4500) as $i) {
        HabrSource::query()->create([
            'source_id' => 90100000 + $i,
            'url' => 'https://habr.com/ru/articles/'.(90100000 + $i).'/',
            'type' => HabrSource::TYPE_ARTICLE,
            'status' => HabrSource::STATUS_PENDING,
        ]);
    }

    (new DispatchHabrFetchJob(limit: 2000))->handle();
    (new DispatchHabrFetchJob(cursor: 90100000 + 2000, limit: 2000))->handle();

    Queue::assertPushed(FetchHabrArticleJob::class, 4000);

    HabrSource::query()
        ->whereIn('source_id', range(90100001, 90102000))
        ->update(['status' => HabrSource::STATUS_FETCHED]);

    (new DispatchHabrFetchJob(cursor: 90100000 + 4000, limit: 2000))->handle();

    Queue::assertPushed(FetchHabrArticleJob::class, 4500);
});

it('admin can stop the crawl: sets the cancel flag and purges the queue', function () {
    Http::fake(['*/api/queues/*' => Http::response('', 204)]);

    $admin = User::query()->create([
        'name' => 'Admin',
        'login' => 'habr_admin_stop',
        'email' => 'habr_admin_stop@test.dev',
        'password' => 'password',
        'is_admin' => true,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/habr/stop')
        ->assertStatus(202)
        ->assertJsonPath('cancel_requested', true);

    expect(app(HabrCrawlState::class)->isCanceled())->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/queues/%2F/'));
});

it('exposes crawl state from the stats endpoint', function () {
    $admin = User::query()->create([
        'name' => 'Admin',
        'login' => 'habr_admin_stats',
        'email' => 'habr_admin_stats@test.dev',
        'password' => 'password',
        'is_admin' => true,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/habr/stats')
        ->assertOk()
        ->assertJsonPath('is_crawling', false)
        ->assertJsonPath('cancel_requested', false);

    $state = app(HabrCrawlState::class);
    $state->start();
    $state->touch();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/habr/stats')
        ->assertOk()
        ->assertJsonPath('is_crawling', true)
        ->assertJsonPath('cancel_requested', false)
        ->assertJsonPath('queued_at', now()->getTimestamp() > 0 ? $state->queuedAt()->toIso8601String() : null);
});

it('cancelled fetch job does not fetch and does not re-dispatch', function () {
    Queue::fake();
    Http::fake(['habr.com/*' => Http::response('ok', 200)]);

    HabrSource::query()->create([
        'source_id' => 900200,
        'url' => 'https://habr.com/ru/news/900200/',
        'type' => HabrSource::TYPE_NEWS,
        'status' => HabrSource::STATUS_PENDING,
    ]);

    app(HabrCrawlState::class)->stop();

    app()->call([new FetchHabrArticleJob(900200), 'handle']);

    expect(HabrSource::query()->where('source_id', 900200)->first()->status)->toBe(HabrSource::STATUS_PENDING);
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

it('cancelled fetch job on 429 does not re-dispatch', function () {
    Queue::fake();
    Http::fake(['habr.com/*' => Http::response('too many', 429, ['Retry-After' => '15'])]);

    HabrSource::query()->create([
        'source_id' => 900201,
        'url' => 'https://habr.com/ru/news/900201/',
        'type' => HabrSource::TYPE_NEWS,
        'status' => HabrSource::STATUS_PENDING,
    ]);

    app(HabrCrawlState::class)->stop();

    app()->call([new FetchHabrArticleJob(900201), 'handle']);

    Queue::assertNothingPushed();
});

it('cancelled dispatch cascade does not enqueue any fetch jobs', function () {
    Queue::fake();

    foreach (range(1, 5) as $i) {
        HabrSource::query()->create([
            'source_id' => 90030000 + $i,
            'url' => 'https://habr.com/ru/articles/'.(90030000 + $i).'/',
            'type' => HabrSource::TYPE_ARTICLE,
            'status' => HabrSource::STATUS_PENDING,
        ]);
    }

    app(HabrCrawlState::class)->stop();

    (new DispatchHabrFetchJob(limit: 100))->handle();

    Queue::assertNothingPushed();
});

it('retry-failed command resets failed sources to pending', function () {
    HabrSource::query()->create([
        'source_id' => 900400,
        'url' => 'https://habr.com/ru/articles/900400/',
        'type' => HabrSource::TYPE_ARTICLE,
        'status' => HabrSource::STATUS_FAILED,
    ]);
    HabrSource::query()->create([
        'source_id' => 900401,
        'url' => 'https://habr.com/ru/articles/900401/',
        'type' => HabrSource::TYPE_ARTICLE,
        'status' => HabrSource::STATUS_FETCHED,
    ]);

    $this->artisan('habr:retry-failed')->assertExitCode(0);

    expect(HabrSource::query()->where('source_id', 900400)->first()->status)->toBe(HabrSource::STATUS_PENDING)
        ->and(HabrSource::query()->where('source_id', 900401)->first()->status)->toBe(HabrSource::STATUS_FETCHED);
});

it('schedules the daily habr update steps', function () {
    $expressions = collect(app(Schedule::class)->events())
        ->mapWithKeys(fn ($event) => [(string) $event->description => $event->expression])
        ->all();

    expect($expressions[DiscoverHabrUrlsJob::class] ?? null)->toBe('0 3 * * *')
        ->and($expressions[DispatchHabrFetchJob::class] ?? null)->toBe('30 3 * * *');

    $retryEvent = collect(app(Schedule::class)->events())
        ->first(fn ($event) => $event->expression === '20 3 * * *' && str_contains((string) $event->command, 'habr:retry-failed'));

    expect($retryEvent)->not->toBeNull();
});
