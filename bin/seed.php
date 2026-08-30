<?php

declare(strict_types=1);

use App\Domain\Entity\Category;
use App\Domain\Entity\Post;
use App\Domain\Entity\Tag;
use App\Domain\Entity\User;
use App\Domain\Repository\CategoryRepositoryInterface;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\Repository\TagRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Value\Email;
use App\Domain\Value\Slug;
use Psr\Container\ContainerInterface;

/**
 * Development seed: 3 authors, PPL curriculum categories/tags and 30 posts
 * about aircraft and PPL lessons. Idempotent — re-running skips what exists.
 *
 * Local development only. It creates login-able accounts with a shared,
 * well-known password and (with --fresh) truncates tables, so it refuses to
 * run against anything that does not look like the local Docker database.
 *
 *   php bin/seed.php            (or: make seed)
 *   php bin/seed.php --fresh    wipe the blog tables first (or: make seed-fresh)
 */

chdir(__DIR__ . '/../');

require 'vendor/autoload.php';

/** @var ContainerInterface $container */
$container = require 'config/container.php';

$db   = $container->get(PhpDb\Adapter\AdapterInterface::class);
$host = (string) ($db->getDriver()->getConnection()->getConnectionParameters()['host'] ?? '');

if (! in_array($host, ['mariadb', 'localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "Refusing to seed: database host '{$host}' is not a recognised local/Docker host.\n");
    exit(1);
}

$users      = $container->get(UserRepositoryInterface::class);
$categories = $container->get(CategoryRepositoryInterface::class);
$tags       = $container->get(TagRepositoryInterface::class);
$posts      = $container->get(PostRepositoryInterface::class);

if (in_array('--fresh', $argv, true)) {
    $db->executeQuery('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['post_tag', 'posts', 'tags', 'categories', 'users'] as $table) {
        $db->executeQuery("TRUNCATE TABLE `{$table}`");
    }
    $db->executeQuery('SET FOREIGN_KEY_CHECKS = 1');
    echo "! wiped blog tables\n";
}

// ---------------------------------------------------------------------------
// Authors (roles TBD)
// ---------------------------------------------------------------------------

// Dev-only shared password for the sample accounts; override with SEED_PASSWORD.
$passwordHash = password_hash(getenv('SEED_PASSWORD') ?: 'password', PASSWORD_BCRYPT);

$authorDefs = [
    ['Julien', 'julien@guittard.me'],
    ['Nadia Fournier', 'nadia@guittard.me'],
    ['Ben Carver', 'ben@guittard.me'],
];

$authorIds = [];
foreach ($authorDefs as [$name, $email]) {
    $existing = $users->findByEmail(Email::fromString($email));
    $user     = $existing ?? $users->save(User::register(Email::fromString($email), $name, $passwordHash));

    $authorIds[] = $user->id;
    printf("%s author  %-16s <%s>  %s\n", $existing ? '=' : '+', $name, $email, $user->id);
}

// ---------------------------------------------------------------------------
// Categories (PPL theory subjects)
// ---------------------------------------------------------------------------

$categoryDefs = [
    'Principles of Flight'       => 'How wings, thrust and control surfaces actually work.',
    'Aircraft General Knowledge' => 'Engines, systems, instruments and the machines we train on.',
    'Air Law'                    => 'Rules, airspace and the paperwork behind every flight.',
    'Meteorology'                => 'Reading the sky, the charts and the forecasts.',
    'Navigation'                 => 'Getting from A to B with map, compass and clock.',
    'Human Performance'          => 'The pilot as a component: physiology and decision-making.',
    'Flight Operations'          => 'Checklists, circuits, procedures and airmanship.',
    'Radio Communications'       => 'Talking to ATC without freezing up.',
];

$categoryIds = [];
foreach ($categoryDefs as $name => $description) {
    $existing = $categories->findBySlug(Slug::fromTitle($name));
    $category = $existing ?? $categories->save(Category::create($name, $description));

    $categoryIds[$name] = $category->id;
    printf("%s category %-28s %s\n", $existing ? '=' : '+', $name, $category->id);
}

// ---------------------------------------------------------------------------
// Posts
// ---------------------------------------------------------------------------

$postDefs = require __DIR__ . '/posts.php';

$publishFrom = new DateTimeImmutable('2026-08-28 08:00:00');
$total       = count($postDefs);
$created     = 0;

foreach ($postDefs as $index => [$title, $categoryName, $tagNames, $excerpt, $body]) {
    if ($posts->findBySlug(Slug::fromTitle($title)) !== null) {
        printf("= post   %s\n", $title);
        continue;
    }

    $slug        = (string) Slug::fromTitle($title);
    $authorId    = $authorIds[$index % 3];
    $categoryId  = $categoryIds[$categoryName];
    $publishedAt = $publishFrom->modify('-' . (($total - 1 - $index) * 9 + 2) . ' days');

    $tagIds = array_map(
        static fn (Tag $tag): string => $tag->id,
        $tags->findOrCreateByNames($tagNames),
    );

    $post = Post::draft($authorId, $title, trimBody($body), $categoryId, $excerpt);
    if (($coverUrl = coverUrl($slug)) !== null) {
        $post = $post->withImage($coverUrl, $title . ' — cover');
    }

    $saved = $posts->save($post->publish($publishedAt), $tagIds);
    $created++;

    printf(
        "+ post   %s [%s] %s  (author %s, %d tags, %s%s)\n",
        $saved->id,
        $categoryName,
        $title,
        $authorId,
        count($tagIds),
        $publishedAt->format('Y-m-d'),
        $coverUrl !== null ? ', image' : '',
    );
}

printf("\nSeed complete: %d authors, %d categories, %d/%d posts created.\n", count($authorIds), count($categoryIds), $created, $total);

exit(0);

/**
 * Public URL of a post's cover, or null for the ~1-in-4 posts that stay
 * image-free. Covers are generated + uploaded separately by `make covers`.
 */
function coverUrl(string $slug): ?string
{
    if (crc32($slug) % 4 === 0) {
        return null;
    }

    $base = rtrim(getenv('S3_PUBLIC_ENDPOINT') ?: 'https://s3.blog.me:8443', '/');

    return $base . '/uploads/covers/' . $slug . '.svg';
}

/** Collapse the heredoc's leading indentation into clean paragraphs. */
function trimBody(string $body): string
{
    $lines = array_map('trim', explode("\n", $body));

    return trim(implode("\n", $lines));
}
