<?php

namespace App\Modules\Content\Services;

/**
 * Checks the hashtags a generated post carries before it can be published.
 *
 * Hashtags were only ever asked for in the prompt and nothing looked at what
 * came back. Publishers send the post body as written, so a hashtag the model
 * made up (a town the business is not in, a trade it does not do) went out
 * under the business's name. A prompt rule the model can ignore is not a
 * guarantee, so the rule is enforced here instead.
 *
 * A hashtag survives only if every word in it can be found in what we know
 * about the business (its name, industry, location, website and reviews) or
 * in a short list of generic words that are safe for any small business.
 * Anything else is dropped, as are tags over the platform's limit and repeats.
 */
class HashtagGuard
{
    /**
     * Words any small business can reasonably tag with, whatever it does.
     *
     * Deliberately short and place-free. A place name only ever passes when it
     * appears in the business's own context, which is the whole point.
     */
    private const GENERIC_WORDS = [
        'a', 'an', 'and', 'the', 'of', 'for', 'in', 'on', 'at', 'to', 'with', 'by', 'our', 'your', 'my', 'we', 'you',
        'uk', 'british', 'local', 'locally', 'small', 'business', 'businesses', 'biz', 'shop', 'support', 'independent',
        'indie', 'family', 'owned', 'run', 'community', 'customer', 'customers', 'client', 'clients', 'review',
        'reviews', 'testimonial', 'testimonials', 'feedback', 'thank', 'thanks', 'thankyou', 'happy', 'service',
        'services', 'quality', 'team', 'friendly', 'trusted', 'expert', 'experts', 'expertise', 'professional',
        'professionals', 'advice', 'tip', 'tips', 'help', 'helpful', 'how', 'guide', 'faq', 'question', 'questions',
        'new', 'news', 'update', 'open', 'book', 'booking', 'bookings', 'now', 'today', 'week', 'weekend', 'monday',
        'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'motivation', 'behind', 'scenes', 'work',
        'job', 'jobs', 'done', 'project', 'projects', 'home', 'homes', 'made', 'handmade', 'craft', 'care', 'value',
    ];

    /** Endings stripped to compare word families: plumbing, plumber, plumbers. */
    private const SUFFIXES = ['ings', 'ing', 'ers', 'er', 'ed', 'es', 's', 'e'];

    /** @var array<string, true> */
    private array $vocabulary = [];

    /** @var array<string, true> */
    private array $stems = [];

    /**
     * @param  string[]  $contextTexts  Everything we know about the business, as plain text.
     */
    public function __construct(array $contextTexts)
    {
        foreach (self::GENERIC_WORDS as $word) {
            $this->vocabulary[$word] = true;
        }

        foreach ($contextTexts as $text) {
            $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string) $text)) ?: [];

            foreach ($words as $word) {
                if ($word !== '') {
                    $this->vocabulary[$word] = true;
                    $this->stems[$this->stem($word)] = true;
                }
            }
        }
    }

    /**
     * Remove hashtags that are unsupported, repeated or over the limit.
     *
     * A tag in a trailing run of hashtags is removed outright. A tag inside a
     * sentence loses only its # so the sentence still reads, because deleting a
     * word out of prose leaves a hole the reader will notice.
     *
     * @return array{content: string, hashtags: string[], removed: string[]}
     */
    public function clean(string $content, int $maxTags): array
    {
        $kept = [];
        $removed = [];
        $seen = [];

        preg_match_all('/(?<![\p{L}\p{N}_&#\/])#([\p{L}\p{N}_]+)/u', $content, $matches, PREG_OFFSET_CAPTURE);

        $decisions = [];
        foreach ($matches[1] as [$tag]) {
            $key = mb_strtolower($tag);

            if (isset($seen[$key])) {
                $decisions[] = false;
                $removed[] = '#'.$tag;
                continue;
            }
            $seen[$key] = true;

            if (count($kept) < $maxTags && $this->isSupported($tag)) {
                $decisions[] = true;
                $kept[] = '#'.$tag;
                continue;
            }

            $decisions[] = false;
            $removed[] = '#'.$tag;
        }

        if ($removed === []) {
            return ['content' => $content, 'hashtags' => $kept, 'removed' => []];
        }

        // Walk the matches back to front so earlier offsets stay valid.
        for ($i = count($matches[0]) - 1; $i >= 0; $i--) {
            if ($decisions[$i]) {
                continue;
            }

            [$full, $offset] = $matches[0][$i];
            $replacement = $this->isInHashtagRun($content, $offset, strlen($full))
                ? ''
                : substr($full, 1);

            $content = substr_replace($content, $replacement, $offset, strlen($full));
        }

        return ['content' => $this->tidy($content), 'hashtags' => $kept, 'removed' => $removed];
    }

    /**
     * Can every part of this tag be accounted for?
     *
     * Tags are usually run together (#mansfieldplumber) or camel cased
     * (#BoilerService), so the tag is split back into known words by dynamic
     * programming rather than on case alone.
     */
    public function isSupported(string $tag): bool
    {
        $tag = mb_strtolower($tag);
        $tag = str_replace('_', '', $tag);
        $length = mb_strlen($tag);

        if ($length === 0) {
            return false;
        }

        // $reachable[$i] is true when the first $i characters split into known words.
        $reachable = array_fill(0, $length + 1, false);
        $reachable[0] = true;

        for ($end = 1; $end <= $length; $end++) {
            for ($start = 0; $start < $end; $start++) {
                if (! $reachable[$start]) {
                    continue;
                }

                if ($this->isKnownPart(mb_substr($tag, $start, $end - $start))) {
                    $reachable[$end] = true;
                    break;
                }
            }
        }

        return $reachable[$length];
    }

    private function isKnownPart(string $part): bool
    {
        if (isset($this->vocabulary[$part])) {
            return true;
        }

        // Numbers (#2026, #247) carry no claim about the business.
        if (preg_match('/^\p{N}+$/u', $part)) {
            return true;
        }

        // Another form of a word the business uses: its site says "plumbing",
        // the tag says "plumber". Generic words are left out of this on purpose,
        // so the loosening only ever applies to the business's own vocabulary.
        return mb_strlen($part) >= 4 && isset($this->stems[$this->stem($part)]);
    }

    private function stem(string $word): string
    {
        foreach (self::SUFFIXES as $suffix) {
            $stemLength = mb_strlen($word) - mb_strlen($suffix);

            if ($stemLength >= 4 && str_ends_with($word, $suffix)) {
                return mb_substr($word, 0, $stemLength);
            }
        }

        return $word;
    }

    /** Is the tag at this offset surrounded only by other hashtags on its line? */
    private function isInHashtagRun(string $content, int $offset, int $length): bool
    {
        $lineStart = strrpos(substr($content, 0, $offset), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $lineEnd = strpos($content, "\n", $offset + $length);
        $lineEnd = $lineEnd === false ? strlen($content) : $lineEnd;

        $line = substr($content, $lineStart, $lineEnd - $lineStart);
        $withoutTags = preg_replace('/#[\p{L}\p{N}_]+/u', '', $line) ?? $line;

        return trim($withoutTags, " \t.,") === '';
    }

    private function tidy(string $content): string
    {
        $lines = explode("\n", $content);
        $lines = array_map(fn ($line) => trim(preg_replace('/[ \t]{2,}/', ' ', $line) ?? $line), $lines);
        $content = implode("\n", $lines);

        // Removing a trailing hashtag line can leave a blank tail behind it.
        $content = preg_replace("/\n{3,}/", "\n\n", $content) ?? $content;

        return trim($content);
    }
}
