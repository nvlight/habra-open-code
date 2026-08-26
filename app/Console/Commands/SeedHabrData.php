<?php

namespace App\Console\Commands;

use App\Enums\PublicationStatus;
use App\Enums\PublicationType;
use App\Models\Comment;
use App\Models\Hub;
use App\Models\Publication;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SeedHabrData extends Command
{
    protected $signature = 'habr:seed
        {--count=50 : Number of publications per type to seed}
        {--types=articles : Comma-separated types to seed: articles, posts, news}
        {--append : Append to existing publications instead of replacing}';

    protected $description = 'Scrape publications and comments from habr.com and seed the database';

    private const FLOWS = [
        'backend' => 'backend',
        'frontend' => 'frontend',
        'admin' => 'admin',
        'ai_and_ml' => 'ai_and_ml',
    ];

    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /** @var array<string, array{id: int, alias: string, name: string}> */
    private array $usersCache = [];

    /** @var array<string, int> */
    private array $hubsCache = [];

    /** @var array<string, int> */
    private array $tagsCache = [];

    public function handle(): int
    {
        $targetCount = (int) $this->option('count');
        $types = array_map('trim', explode(',', (string) $this->option('types')));
        $append = $this->option('append');

        $this->info('Types: '.implode(', ', $types).' | Count: '.$targetCount.' | Append: '.($append ? 'yes' : 'no'));

        if (! $append) {
            $this->info('Step 1/4: Deleting existing publications...');
            $this->deletePublications();
        } else {
            $this->info('Step 1/4: Appending to existing publications...');
        }

        $this->info('Step 2/4: Collecting IDs from RSS feeds...');
        $allItems = $this->collectIds($types, $targetCount);

        $this->info('Step 3/4: Scraping publications and comments...');
        $articles = $this->scrapePublications($allItems);

        $this->info('Step 4/4: Seeding database...');
        $this->seedDatabase($articles);

        $this->newLine();
        $this->info('Done!');

        return self::SUCCESS;
    }

    private function deletePublications(): void
    {
        DB::statement('TRUNCATE TABLE comments CASCADE');
        DB::statement('TRUNCATE TABLE publications CASCADE');
        $this->line('  Truncated publications and comments.');
    }

    /**
     * @param  list<string>  $types
     * @return list<array{id: string, type: string}>
     */
    private function collectIds(array $types, int $targetCount): array
    {
        /** @var list<array{id: string, type: string}> $allItems */
        $allItems = [];
        $perType = $targetCount;

        foreach ($types as $type) {
            $ids = $this->collectIdsForType($type, $perType);
            foreach ($ids as $id) {
                $allItems[] = ['id' => $id, 'type' => $type];
            }
        }

        $allItems = $this->deduplicateItems($allItems);
        $this->line('  Total unique items to scrape: '.count($allItems));

        return $allItems;
    }

    /**
     * @return list<string>
     */
    private function collectIdsForType(string $type, int $perType): array
    {
        $allIds = [];

        foreach (self::FLOWS as $flowAlias => $flowName) {
            $rssUrl = "https://habr.com/ru/rss/flows/{$flowAlias}/{$type}/?fl=ru";
            $this->line("  Fetching {$type} RSS for flow: {$flowName}...");
            $xml = $this->fetchUrl($rssUrl);

            if ($xml === null) {
                $this->warn("  Failed to fetch RSS for {$flowName}/{$type}");

                continue;
            }

            $ids = $this->parseRssIds($xml, $type);
            $this->line('  Found '.count($ids)." {$type} in {$flowName}");
            $allIds = array_merge($allIds, $ids);
        }

        $allIds = array_unique($allIds);
        $sliced = array_slice($allIds, 0, $perType);
        $this->line('  Selected '.count($sliced)." {$type} for seeding");

        return $sliced;
    }

    /**
     * @param  list<array{id: string, type: string}>  $items
     * @return list<array{id: string, type: string}>
     */
    private function deduplicateItems(array $items): array
    {
        $seen = [];

        return array_filter($items, function (array $item) use (&$seen): bool {
            $key = $item['id'];

            if (isset($seen[$key])) {
                return false;
            }

            $seen[$key] = true;

            return true;
        });
    }

    /**
     * @param  list<array{id: string, type: string}>  $allItems
     * @return list<array{pinia: array, comments: array, rss_tags: list<string>}>
     */
    private function scrapePublications(array $allItems): array
    {
        $articles = [];
        $total = count($allItems);

        foreach ($allItems as $i => $item) {
            $id = $item['id'];
            $type = $item['type'];
            $progress = ($i + 1).'/'.$total;
            $this->line("  [{$progress}] Fetching {$type} {$id}...");

            $html = $this->fetchUrl("https://habr.com/ru/{$type}/{$id}/");

            if ($html === null) {
                $this->warn("  Failed to fetch {$type} {$id}, skipping");

                continue;
            }

            $pinia = $this->extractPiniaState($html);

            if ($pinia === null) {
                $this->warn("  Failed to extract PINIA state for {$type} {$id}, skipping");

                continue;
            }

            $articleData = $this->findArticleInPinia($pinia, $id);

            if ($articleData === null) {
                $this->warn("  {$type} {$id} not found in PINIA state, skipping");

                continue;
            }

            $comments = $this->fetchComments($id);
            $rssTags = $this->extractRssTagsFromPinia($pinia, $id);

            $articles[] = [
                'pinia' => $articleData,
                'comments' => $comments,
                'rss_tags' => $rssTags,
            ];

            usleep(200_000);
        }

        $this->line('  Scraped '.count($articles).' publications successfully');

        return $articles;
    }

    private function seedDatabase(array $articles): void
    {
        $admin = User::query()->where('login', 'admin')->first();

        $existingHubs = Hub::query()->pluck('id', 'alias')->toArray();
        $this->hubsCache = $existingHubs;

        $this->line('  Creating '.count($articles).' publications...');

        foreach ($articles as $article) {
            $pinia = $article['pinia'];
            $commentsData = $article['comments'];
            $rssTags = $article['rss_tags'];

            $authorData = $pinia['author'] ?? null;
            $author = $this->getOrCreateUser($authorData, $admin);

            $publication = $this->createPublication($pinia, $author);

            $hubAliases = array_column($pinia['hubs'] ?? [], 'alias');
            $this->syncHubs($publication, $hubAliases);

            $tagNames = array_merge(
                $rssTags,
                $this->extractTagNames($pinia)
            );
            $this->syncTags($publication, $tagNames);

            $this->createComments($commentsData, $publication, $admin);

            $this->line("  [{$publication->id}] [{$publication->type->value}] {$publication->title}");
        }

        $this->recalculateCounters();
    }

    private function fetchUrl(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8',
            ])->timeout(15)->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function parseRssIds(string $xml, string $type): array
    {
        $ids = [];
        $pattern = '/<guid[^>]*>https:\/\/habr\.com\/ru\/(?:articles|posts|news|companies\/[^\/]+\/(?:articles|posts|news))\/(\d+)\//';
        preg_match_all($pattern, $xml, $matches);

        return array_unique($matches[1]);
    }

    private function extractPiniaState(string $html): ?array
    {
        if (! preg_match('/window\.__PINIA_STATE__\s*=\s*(\{.*?\});\s*\(/s', $html, $matches)) {
            return null;
        }

        $json = $matches[1];

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : null;
    }

    private function findArticleInPinia(array $pinia, string $id): ?array
    {
        $articles = $pinia['articlesList']['articlesList'] ?? [];

        if (isset($articles[$id])) {
            return $articles[$id];
        }

        foreach ($articles as $articleId => $article) {
            if ((string) $articleId === $id) {
                return $article;
            }
        }

        return null;
    }

    /**
     * @return array{id: string, message: string, score: int, timePublished: string, author: array{id: string, alias: string}, parentId: string|null, children: list<string>}[]
     */
    private function fetchComments(string $articleId): array
    {
        $url = "https://habr.com/kek/v2/articles/{$articleId}/comments?fl=ru&hl=ru";
        $json = $this->fetchUrl($url);

        if ($json === null) {
            return [];
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $comments = $data['comments'] ?? [];

        return array_values($comments);
    }

    /**
     * @return list<string>
     */
    private function extractRssTagsFromPinia(array $pinia, string $id): array
    {
        $articles = $pinia['articlesList']['articlesList'] ?? [];

        if (! isset($articles[$id])) {
            return [];
        }

        $article = $articles[$id];
        $hubs = $article['hubs'] ?? [];

        return array_map(
            fn (array $hub) => $hub['titleHtml'] ?? $hub['title'] ?? $hub['alias'] ?? '',
            $hubs
        );
    }

    /**
     * @return list<string>
     */
    private function extractTagNames(array $pinia): array
    {
        $articles = $pinia['articlesList']['articlesList'] ?? [];
        $article = reset($articles);

        if ($article === false) {
            return [];
        }

        $tags = $article['tags'] ?? [];

        return array_map(
            fn (array $tag) => $tag['titleHtml'] ?? $tag['alias'] ?? $tag['title'] ?? '',
            $tags
        );
    }

    private function getOrCreateUser(?array $authorData, ?User $admin): User
    {
        if ($authorData === null) {
            return $admin ?? User::firstOrFail();
        }

        $habrId = (string) ($authorData['id'] ?? '');
        $alias = $authorData['alias'] ?? 'unknown';

        if (isset($this->usersCache[$habrId])) {
            $user = User::find((int) $this->usersCache[$habrId]);

            if ($user === null) {
                unset($this->usersCache[$habrId]);
            } else {
                return $user;
            }
        }

        $login = preg_replace('/[^a-zA-Z0-9_]/', '_', $alias);
        $login = substr($login, 0, 39) ?: 'user_'.$habrId;

        $existing = User::query()->where('login', $login)->first();

        if ($existing !== null) {
            $this->usersCache[$habrId] = $existing->id;

            return $existing;
        }

        $fullname = $authorData['fullname'] ?? null;
        $name = $fullname ?? ucfirst($alias);
        $email = strtolower($login).'_'.$habrId.'@habr.local';

        $user = User::query()->create([
            'name' => $name,
            'login' => $login,
            'email' => $email,
            'password' => 'password',
            'about' => $authorData['speciality'] ?? null,
            'avatar' => $this->resolveAvatarUrl($authorData['avatarUrl'] ?? null),
            'karma' => max(0, (int) ($authorData['scoreStats']['score'] ?? 0)),
            'rating' => (float) ($authorData['rating'] ?? 0),
        ]);

        $this->usersCache[$habrId] = $user->id;

        return $user;
    }

    private function resolveAvatarUrl(?string $avatarUrl): ?string
    {
        if ($avatarUrl === null || $avatarUrl === '') {
            return null;
        }

        if (str_starts_with($avatarUrl, '//')) {
            return 'https:'.$avatarUrl;
        }

        if (str_starts_with($avatarUrl, '/')) {
            return 'https://habr.com'.$avatarUrl;
        }

        return $avatarUrl;
    }

    private function createPublication(array $pinia, User $author): Publication
    {
        $typeMap = [
            'article' => PublicationType::Article,
            'post' => PublicationType::Post,
            'news' => PublicationType::News,
        ];

        $pubType = $pinia['publicationType'] ?? $pinia['postType'] ?? null;
        $type = $typeMap[$pubType] ?? PublicationType::Article;
        $stats = $pinia['statistics'] ?? [];
        $publishedAt = $this->parseHabrDate($pinia['timePublished'] ?? null);

        $body = $pinia['textHtml'] ?? '';
        $lead = $this->extractLeadFromPinia($pinia);

        $readingTime = max(1, (int) (mb_strlen(strip_tags($body)) / 2000));

        return Publication::query()->create([
            'user_id' => $author->id,
            'type' => $type,
            'status' => PublicationStatus::Published,
            'title' => $this->extractTitle($pinia),
            'lead' => $lead,
            'body' => $body,
            'difficulty' => $type === PublicationType::Article
                ? $this->estimateDifficulty($body)
                : null,
            'label' => $type === PublicationType::Article
                ? $this->guessLabel($pinia)
                : null,
            'is_translation' => false,
            'reading_time' => $readingTime,
            'views_count' => max(0, (int) ($stats['readingCount'] ?? 0)),
            'reach' => max(0, (int) ($stats['reach'] ?? 0)),
            'rating' => (int) ($stats['score'] ?? 0),
            'votes_up' => max(0, (int) ($stats['votesCountPlus'] ?? 0)),
            'votes_down' => max(0, (int) ($stats['votesCountMinus'] ?? 0)),
            'comments_count' => 0,
            'bookmarks_count' => max(0, (int) ($stats['favoritesCount'] ?? 0)),
            'published_at' => $publishedAt,
        ]);
    }

    private function extractLeadFromPinia(array $pinia): ?string
    {
        $leadData = $pinia['leadData'] ?? null;

        if ($leadData === null) {
            return null;
        }

        $textHtml = $leadData['textHtml'] ?? '';

        if ($textHtml !== '') {
            return $textHtml;
        }

        return null;
    }

    private function extractTitle(array $pinia): string
    {
        if (! empty($pinia['titleHtml'])) {
            return $pinia['titleHtml'];
        }

        $preview = $pinia['previewHtml'] ?? '';

        if ($preview !== '') {
            $text = strip_tags($preview);
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);

            if ($text !== '') {
                return mb_substr($text, 0, 500);
            }
        }

        $schemaJson = $pinia['metadata']['schemaJsonLd'] ?? null;

        if ($schemaJson !== null) {
            $schema = json_decode(is_string($schemaJson) ? $schemaJson : '', true);

            if (is_array($schema) && ! empty($schema['headline'])) {
                return $schema['headline'];
            }
        }

        return 'Untitled';
    }

    private function estimateDifficulty(string $body): string
    {
        $length = mb_strlen(strip_tags($body));

        if ($length < 3000) {
            return 'easy';
        }

        if ($length < 8000) {
            return 'medium';
        }

        return 'hard';
    }

    private function guessLabel(array $pinia): string
    {
        $title = strtolower($this->extractTitle($pinia));

        if (str_contains($title, 'как') || str_contains($title, 'tutorial')) {
            return 'tutorial';
        }

        if (str_contains($title, 'обзор') || str_contains($title, 'review')) {
            return 'review';
        }

        if (str_contains($title, 'аналитик') || str_contains($title, 'сравнен')) {
            return 'analytics';
        }

        return 'opinion';
    }

    private function parseHabrDate(?string $dateString): Carbon
    {
        if ($dateString === null) {
            return now();
        }

        try {
            return Carbon::parse($dateString);
        } catch (\Throwable) {
            return now();
        }
    }

    private function syncHubs(Publication $publication, array $hubAliases): void
    {
        $hubIds = [];

        foreach ($hubAliases as $alias) {
            $alias = trim($alias);

            if ($alias === '') {
                continue;
            }

            if (isset($this->hubsCache[$alias])) {
                $hubIds[] = $this->hubsCache[$alias];

                continue;
            }

            $hub = Hub::query()->firstOrCreate(
                ['alias' => $alias],
                [
                    'name' => $alias,
                    'description' => null,
                ]
            );

            $this->hubsCache[$alias] = $hub->id;
            $hubIds[] = $hub->id;
        }

        $publication->hubs()->sync($hubIds);
    }

    private function syncTags(Publication $publication, array $tagNames): void
    {
        $tagIds = [];

        foreach ($tagNames as $name) {
            $name = trim($name);

            if ($name === '' || mb_strlen($name) > 100) {
                continue;
            }

            $key = strtolower($name);

            if (isset($this->tagsCache[$key])) {
                $tagIds[] = $this->tagsCache[$key];

                continue;
            }

            $tag = Tag::query()->firstOrCreate(['name' => $name]);
            $this->tagsCache[$key] = $tag->id;
            $tagIds[] = $tag->id;
        }

        $publication->tags()->sync(array_unique($tagIds));
    }

    private function createComments(array $commentsData, Publication $publication, ?User $admin): void
    {
        if (empty($commentsData)) {
            return;
        }

        $commentMap = [];

        $sorted = $this->sortCommentsByLevel($commentsData);

        foreach ($sorted as $commentData) {
            $authorData = $commentData['author'] ?? null;
            $author = $this->getOrCreateUser($authorData, $admin);

            $parentId = null;

            if (! empty($commentData['parentId']) && isset($commentMap[$commentData['parentId']])) {
                $parentId = $commentMap[$commentData['parentId']];
            }

            $body = $commentData['message'] ?? '';

            if ($body === '') {
                continue;
            }

            $comment = Comment::query()->create([
                'publication_id' => $publication->id,
                'user_id' => $author->id,
                'parent_id' => $parentId,
                'body' => $body,
                'rating' => (int) ($commentData['score'] ?? 0),
                'created_at' => $this->parseHabrDate($commentData['timePublished'] ?? null),
                'updated_at' => null,
            ]);

            $commentMap[$commentData['id']] = $comment->id;
        }

        $publication->update([
            'comments_count' => $publication->comments()->count(),
        ]);
    }

    /**
     * @param  list<array>  $comments
     * @return list<array>
     */
    private function sortCommentsByLevel(array $comments): array
    {
        usort($comments, function (array $a, array $b): int {
            $levelA = $a['level'] ?? 0;
            $levelB = $b['level'] ?? 0;

            if ($levelA !== $levelB) {
                return $levelA <=> $levelB;
            }

            return ($a['timePublished'] ?? '') <=> ($b['timePublished'] ?? '');
        });

        return $comments;
    }

    private function recalculateCounters(): void
    {
        $this->line('  Recalculating publication counters...');

        DB::statement('
            UPDATE publications
            SET comments_count = (
                SELECT COUNT(*) FROM comments WHERE comments.publication_id = publications.id
            )
        ');

        DB::statement('
            UPDATE hubs
            SET subscribers_count = (
                SELECT COUNT(*) FROM hub_publication WHERE hub_publication.hub_id = hubs.id
            )
        ');
    }
}
