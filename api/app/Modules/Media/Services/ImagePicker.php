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
 *  1. Only enabled photos that vetting has passed as a photo or illustration,
 *     and never AI images. Those are in the library so the owner can see them,
 *     not to be recycled: a generated picture seen twice reads as stock.
 *  2. Nothing used in the last 21 days, unless everything has been, in which
 *     case the least recently used is still better than no photo.
 *  3. A photo from the website page the post was written from, so a post about
 *     boiler servicing shows the photo from the boiler servicing page.
 *  4. Then the photo whose description best matches what the post says, so an
 *     SEO post gets the desk shot rather than the office dog.
 *  5. Then one the owner chose deliberately: their Google profile photos and
 *     their own uploads, ahead of website photos.
 *  6. Then real photos ahead of illustrations.
 *  7. Within all that, least recently used first (never used before all), and
 *     ties broken at random so a fresh library does not always lead with the
 *     same photo.
 */
class ImagePicker
{
    public const REUSE_GAP_DAYS = 21;

    // Words too common to say anything about what a photo shows.
    private const STOPWORDS = [
        'this', 'that', 'with', 'from', 'your', 'have', 'they', 'their', 'there', 'what', 'when', 'where',
        'which', 'about', 'into', 'over', 'just', 'more', 'most', 'some', 'than', 'then', 'them', 'these',
        'those', 'will', 'would', 'could', 'should', 'been', 'being', 'were', 'also', 'very', 'each', 'other',
        'like', 'made', 'make', 'shows', 'showing', 'image', 'photo', 'picture', 'visible', 'background',
        'front', 'side', 'small', 'large', 'business', 'customer', 'customers', 'people', 'person',
    ];

    /**
     * @param  array<int, array{id: string, source: string, page_url: ?string, is_enabled: bool, last_used_at: ?CarbonInterface, vetted?: bool, kind?: ?string, description?: ?string}>  $images
     * @param  callable(int, int): int|null  $random  Returns an int in [min, max]; tests pass a fixed one.
     * @param  string|null  $postText  What the post says, matched against photo descriptions.
     * @return array<string, mixed>|null  The chosen image, as given.
     */
    public function choose(array $images, ?string $pageUrl, CarbonInterface $now, ?callable $random = null, ?string $postText = null): ?array
    {
        $random ??= 'random_int';

        $eligible = array_values(array_filter(
            $images,
            fn (array $image) => ($image['is_enabled'] ?? false)
                && ($image['source'] ?? null) !== 'ai'
                && ($image['vetted'] ?? true)
                && in_array($image['kind'] ?? 'photo', ['photo', 'illustration'], true)
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

        $postWords = $postText ? self::words($postText) : [];

        $samePage = fn (array $image): bool => $page !== null
            && ! empty($image['page_url'])
            && self::normaliseUrl($image['page_url']) === $page;

        $relevance = fn (array $image): int => $postWords === []
            ? 0
            : count(array_intersect_key(self::words((string) ($image['description'] ?? '')), $postWords));

        $ownerChosen = fn (array $image): bool => in_array($image['source'], ['google', 'upload'], true);
        $isPhoto = fn (array $image): bool => ($image['kind'] ?? 'photo') === 'photo';

        // Shuffle first with the injected randomness, then sort stably, so equal
        // candidates come out in a random but reproducible-under-test order.
        for ($i = count($pool) - 1; $i > 0; $i--) {
            $j = $random(0, $i);
            [$pool[$i], $pool[$j]] = [$pool[$j], $pool[$i]];
        }

        usort($pool, function (array $a, array $b) use ($samePage, $relevance, $ownerChosen, $isPhoto) {
            foreach ([$samePage, $relevance, $ownerChosen, $isPhoto] as $rule) {
                // Higher first: true before false, more matching words before fewer.
                $byRule = $rule($b) <=> $rule($a);
                if ($byRule !== 0) {
                    return $byRule;
                }
            }

            $aUsed = $a['last_used_at']?->getTimestamp() ?? PHP_INT_MIN;
            $bUsed = $b['last_used_at']?->getTimestamp() ?? PHP_INT_MIN;

            return $aUsed <=> $bUsed;
        });

        return $pool[0];
    }

    /**
     * The meaningful word stems in a piece of text, as a set.
     *
     * Deliberately crude: lower case, four letters or more, common words out,
     * and plural or -ing endings trimmed so "boilers" matches "boiler" and
     * "servicing" matches "service". Good enough to rank a handful of photos.
     *
     * @return array<string, true>
     */
    public static function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $set = [];

        foreach ($words as $word) {
            if (mb_strlen($word) < 4 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }

            $stem = preg_replace('/(ings|ing|ers|er|es|s|e)$/u', '', $word) ?? $word;
            $set[mb_strlen($stem) >= 3 ? $stem : $word] = true;
        }

        return $set;
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
