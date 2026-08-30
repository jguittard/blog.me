<?php

declare(strict_types=1);

use App\Domain\Value\Slug;

/**
 * Generates a deterministic cover SVG for every post that gets an image and
 * writes it to data/covers/<slug>.svg. `make covers` then uploads the folder to
 * the MinIO `uploads` bucket. Kept in step with bin/seed.php's coverUrl().
 *
 *   php bin/covers.php
 */

chdir(__DIR__ . '/../');

require 'vendor/autoload.php';

/** @var list<array{0:string,1:string,2:list<string>,3:string,4:string}> $postDefs */
$postDefs = require __DIR__ . '/posts.php';

$dir = 'data/covers';
if (! is_dir($dir) && ! mkdir($dir, 0o775, true) && ! is_dir($dir)) {
    fwrite(STDERR, "Cannot create {$dir}\n");
    exit(1);
}

$written = 0;
foreach ($postDefs as [$title]) {
    $slug = (string) Slug::fromTitle($title);

    // Same 1-in-4 skip rule as bin/seed.php::coverUrl().
    if (crc32($slug) % 4 === 0) {
        continue;
    }

    file_put_contents("{$dir}/{$slug}.svg", renderCover($slug));
    $written++;
    echo "+ {$slug}.svg\n";
}

printf("\n%d covers written to %s/\n", $written, $dir);
exit(0);

/** A calm, deterministic cover in the site's slate/blue palette. No text. */
function renderCover(string $slug): string
{
    $h = crc32($slug);
    $n = static fn (int $shift): int => ($h >> $shift) & 0xFF;

    $hues = [204, 210, 214, 218, 222, 199, 226];
    $hue  = $hues[$h % count($hues)];
    $hue2 = ($hue + 22) % 360;

    $bg1  = "hsl({$hue} 42% 96%)";
    $bg2  = "hsl({$hue} 46% 89%)";
    $ink  = "hsl({$hue} 55% 60%)";
    $ink2 = "hsl({$hue2} 50% 68%)";

    $cx  = 260 + $n(0) * 3;
    $cy  = 120 + $n(3) % 260;
    $cr  = 170 + $n(6) % 170;
    $dcx = $cx + 44;
    $dcy = $cy + 18;

    $tx = 640 + $n(9) % 380;
    $ty = 360 + $n(12) % 190;
    $ts = 170 + $n(15) % 150;

    $ly  = 210 + $n(18) % 240;
    $ly2 = $ly - 70;

    return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 630" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Cover illustration">
            <defs>
                <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="{$bg1}" />
                    <stop offset="1" stop-color="{$bg2}" />
                </linearGradient>
            </defs>
            <rect width="1200" height="630" fill="url(#bg)" />
            <circle cx="{$cx}" cy="{$cy}" r="{$cr}" fill="{$ink}" fill-opacity="0.13" />
            <path d="M{$tx} {$ty} l{$ts} -{$ts} l{$ts} {$ts} z" fill="{$ink2}" fill-opacity="0.16" />
            <line x1="-40" y1="{$ly}" x2="1240" y2="{$ly2}" stroke="{$ink}" stroke-opacity="0.30" stroke-width="6" />
            <circle cx="{$dcx}" cy="{$dcy}" r="9" fill="{$ink}" fill-opacity="0.55" />
        </svg>
        SVG;
}
