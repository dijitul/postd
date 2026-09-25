<?php

namespace App\Modules\Media\Services;

use Carbon\CarbonInterface;

/**
 * Chooses which library photo a new post should carry.
 *
 * Pure: it is handed plain arrays and a clock, so the rules can be tested
 * without a database. ImageLibraryService feeds it the business's images and
 * records the use of whichever one comes back.
 *
 * In order:
 *  1. Only enabled photos, and never AI images. Those are in the library so the
 *     owner can see them, not to be recycled: a generated picture seen twice
 *     reads as stock.
 *  2. Nothing used in the last 21 days, unless everything has been, in which
 *     case the least recently used is still better than no photo.
 *  3. A photo from the website page the post was written from, so a post about
 *     boiler servicing shows the photo from the boiler servicing page.
 *  4. Otherwise one the owner chose deliberately: their Google profile photos
 *     and their own uploads.
 *  5. Otherwise anything.
 *  6. Within a tier, least recently used first (never used before all), and
 *     ties broken at random so a fresh library does not always lead with the
 *     same photo.
 */
class ImagePicker
{
    public const REUSE_GAP_DAYS = 21;

    /**
     * @param  array<int, array{id: string, source: string, page_url: ?string, is_enabled: bool, last_used_at: ?CarbonInterface}>  $images
     * @param  callable(int, int): int|null  $random  Returns an int in [min, max]; tests pass a fixed one.
     * @return array<string, mixed>|null  The chosen image, as given.
     */
    public function choose(array $images, ?string $pageUrl, CarbonInterface $now, ?callable $random = null): ?array
    {
        $random ??= 'random_int';

        $eligible = array_values(array_filter(
            $images,
            fn (array $image) => ($image['is_enabled'] ?? false) && ($image['source'] ?? null) !== 'ai'
        ));

        if ($eligible === []) {
            return null;
        }

        $cutoff = $now->copy()->subDays(self::REUSE_GAP_DAYS);
        $rested = array_values(array_filter(
            $eligible,
            fn (array $image) => $image['last_used_at'] === null || $image['last_used_at']->lessThan($cutoff)
        ));

        $pool = $rested ?: $eligible;
        $page = $pageUrl ? self::normaliseUrl($pageUrl) : null;

        $tier = function (array $image) use ($page): int {
            if ($page !== null && ! empty($image['page_url']) && self::normaliseUrl($image['page_url']) === $page) {
                return 0;
            }

            return in_array($image['source'], ['google', 'upload'], true) ? 1 : 2;
        };

        // Shuffle first with the injected randomness, then sort stably, so equal
        // candidates come out in a random but reproducible-under-test order.
        for ($i = count($pool) - 1; $i > 0; $i--) {
            $j = $random(0, $i);
            [$pool[$i], $pool[$j]] = [$pool[$j], $pool[$i]];
        }

        usort($pool, function (array $a, array $b) use ($tier) {
            $byTier = $tier($a) <=> $tier($b);
            if ($byTier !== 0) {
                return $byTier;
            }

            $aUsed = $a['last_used_at']?->getTimestamp() ?? PHP_INT_MIN;
            $bUsed = $b['last_used_at']?->getTimestamp() ?? PHP_INT_MIN;

            return $aUsed <=> $bUsed;
        });

        return $pool[0];
    }

    /**
     * Compare pages without caring about scheme, www, trailing slash, query or case,
     * since the same page is linked all of those ways across one site.
     */
    public static function normaliseUrl(string $url): string
    {
        $parts = parse_url(strtolower(trim($url)));
        $host = preg_replace('/^www\./', '', $parts['host'] ?? '');
        $path = rtrim($parts['path'] ?? '', '/');

        return $host.$path;
    }
}
