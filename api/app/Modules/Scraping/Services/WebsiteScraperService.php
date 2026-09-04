<?php

namespace App\Modules\Scraping\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class WebsiteScraperService
{
    private readonly Client $httpClient;

    // Elements that are never useful — skip them
    private const SKIP_TAGS = ['script', 'style', 'noscript', 'iframe', 'svg', 'img', 'nav', 'footer'];

    // Common UK business opening hours patterns
    private const HOURS_PATTERNS = [
        '/(?:mon|tue|wed|thu|fri|sat|sun)[a-z]*\.?\s*[-–to]+\s*(?:mon|tue|wed|thu|fri|sat|sun)[a-z]*\.?\s*:?\s*\d{1,2}(?::\d{2})?(?:am|pm)?\s*[-–to]+\s*\d{1,2}(?::\d{2})?(?:am|pm)?/i',
        '/\d{1,2}(?::\d{2})?\s*(?:am|pm)\s*[-–to]+\s*\d{1,2}(?::\d{2})?\s*(?:am|pm)/i',
    ];

    // Contact info patterns
    // class/id/role values that mark site chrome rather than page content
    private const CHROME_ATTR_PATTERN = '/\b(nav|navbar|navigation|menu|breadcrumb|header|footer|sidebar|widget|cookie|consent|banner|social|share|pagination|offcanvas|drawer|topbar|utility)\b/';

    // Navigation labels, CTAs and legal furniture that look like list items
    private const BOILERPLATE_PATTERN = '/^(home|about( us)?|contact( us)?|blog|news|our (blog|news|team|services|work)|services|portfolio|gallery|testimonials?|reviews?|faqs?|frequently asked questions|privacy( policy)?|terms( (and|&) conditions)?|cookie[s]?( policy)?|sitemap|log ?in|sign ?[iu]n|register|search|menu|close|next|previous|prev|back|more|read more|learn more|find out more|get (in touch|a quote|started)|book (now|online)|call (us|now)|email us|enquire( now)?|subscribe|follow us|share|skip to (main )?content|all rights reserved|copyright.*)$/';

    // Per-page copy kept for quoting. Generous enough to hold a real page, small
    // enough that three of them do not bloat the content source row.
    private const MAX_PAGE_TEXT_CHARS = 2000;

    private const PHONE_PATTERN = '/(?:\+44|0)[\s\-]?\d{2,4}[\s\-]?\d{3,4}[\s\-]?\d{3,4}/';
    private const EMAIL_PATTERN = '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/';

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 15,
            'connect_timeout' => 10,
            'allow_redirects' => ['max' => 5],
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; postd.uk/1.0; +https://postd.uk/bot)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-GB,en;q=0.5',
            ],
            'verify' => true,
        ]);
    }

    /**
     * Scrape a business website and return structured data for AI use.
     *
     * @return array{
     *   page_title: string|null,
     *   meta_description: string|null,
     *   h1_headings: string[],
     *   h2_headings: string[],
     *   body_text: string,
     *   services: string[],
     *   key_phrases: string[],
     *   opening_hours: string[],
     *   phone_numbers: string[],
     *   email_addresses: string[],
     *   location_mentions: string[],
     *   page_text: array<int, array{url: string, title: string|null, text: string}>,
     *   scraped_at: string,
     *   url: string,
     * }
     */
    public function scrape(string $url): array
    {
        $url = $this->normaliseUrl($url);

        try {
            $response = $this->httpClient->get($url);
            $html = (string) $response->getBody();
        } catch (RequestException $e) {
            Log::warning("WebsiteScraperService: Failed to fetch {$url}", [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return $this->emptyResult($url);
        }

        $crawler = new Crawler($html);

        $data = [
            'url' => $url,
            'scraped_at' => now()->toIso8601String(),
            'page_title' => $this->extractTitle($crawler),
            'meta_description' => $this->extractMetaDescription($crawler),
            'h1_headings' => $this->extractHeadings($crawler, 'h1'),
            'h2_headings' => $this->extractHeadings($crawler, 'h2'),
            'body_text' => $this->extractBodyText($crawler),
        ];

        // Derive higher-level insights from the raw text
        $allText = implode(' ', array_filter([
            $data['page_title'],
            $data['meta_description'],
            implode(' ', $data['h1_headings']),
            implode(' ', $data['h2_headings']),
            $data['body_text'],
        ]));

        $data['services'] = $this->extractServices($crawler, $allText);
        $data['key_phrases'] = $this->extractKeyPhrases($data);
        $data['opening_hours'] = $this->extractOpeningHours($allText);
        $data['phone_numbers'] = $this->extractPhoneNumbers($allText);
        $data['email_addresses'] = $this->extractEmailAddresses($allText);
        $data['location_mentions'] = $this->extractLocationMentions($allText);

        // Keep each page's copy separate as well as concatenated. Content generation
        // quotes lines from the site back at readers, and a quote is worth more when
        // it can be credited to the page it came from. body_text stays as it was
        // because everything else downstream reads it.
        $data['page_text'] = [[
            'url'   => $url,
            'title' => $data['page_title'],
            'text'  => $this->trimBodyText($data['body_text'], self::MAX_PAGE_TEXT_CHARS),
        ]];

        // Also try scraping the /about or /services page if they exist
        $additionalPages = $this->discoverAdditionalPages($crawler, $url);
        foreach (array_slice($additionalPages, 0, 2) as $pageUrl) {
            $additionalData = $this->scrapeAdditionalPage($pageUrl);
            if ($additionalData) {
                $data['services'] = array_unique(array_merge($data['services'], $additionalData['services']));
                $data['body_text'] .= ' ' . $additionalData['body_text'];

                if (trim($additionalData['body_text']) !== '') {
                    $data['page_text'][] = [
                        'url'   => $pageUrl,
                        'title' => $additionalData['page_title'],
                        'text'  => $this->trimBodyText($additionalData['body_text'], self::MAX_PAGE_TEXT_CHARS),
                    ];
                }
            }
        }

        // Trim body text to avoid sending too many tokens to AI
        $data['body_text'] = $this->trimBodyText($data['body_text'], 3000);

        return $data;
    }

    private function extractTitle(Crawler $crawler): ?string
    {
        try {
            return trim($crawler->filter('title')->text(''));
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractMetaDescription(Crawler $crawler): ?string
    {
        try {
            $meta = $crawler->filter('meta[name="description"]');
            if ($meta->count() > 0) {
                return trim($meta->attr('content') ?? '');
            }

            // Try OG description as fallback
            $og = $crawler->filter('meta[property="og:description"]');
            if ($og->count() > 0) {
                return trim($og->attr('content') ?? '');
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function extractHeadings(Crawler $crawler, string $tag): array
    {
        try {
            $headings = [];
            $crawler->filter($tag)->each(function (Crawler $node) use (&$headings) {
                $text = trim($node->text(''));
                if ($text && strlen($text) > 3 && strlen($text) < 200) {
                    $headings[] = $text;
                }
            });
            return array_unique(array_slice($headings, 0, 20));
        } catch (\Throwable) {
            return [];
        }
    }

    private function extractBodyText(Crawler $crawler): string
    {
        try {
            // Remove unwanted elements first
            $html = $crawler->filter('body')->html('');
            $cleanCrawler = new Crawler('<body>'.$html.'</body>');

            // Build text from paragraphs, list items, and divs — avoiding nav/footer
            $textParts = [];

            $cleanCrawler->filter('p, li, td, h3, h4, h5')->each(function (Crawler $node) use (&$textParts) {
                // Skip if inside a nav, footer, or header
                $ancestors = ['nav', 'footer', 'header'];
                foreach ($ancestors as $ancestor) {
                    try {
                        $node->closest($ancestor);
                        return; // skip
                    } catch (\Throwable) {
                    }
                }

                $text = trim($node->text(''));
                if ($text && strlen($text) > 20) {
                    $textParts[] = $text;
                }
            });

            return implode(' ', $textParts);
        } catch (\Throwable) {
            return '';
        }
    }

    private function extractServices(Crawler $crawler, string $allText): array
    {
        $services = [];

        // Look for service-like lists and headings, ignoring nav/footer chrome —
        // otherwise menu items and footer links crowd out the real services.
        try {
            $crawler->filter('ul li, ol li')->each(function (Crawler $node) use (&$services) {
                $text = trim($node->text(''));
                if (strlen($text) > 5 && strlen($text) < 100
                    && ! $this->isInPageChrome($node)
                    && ! $this->isBoilerplate($text)) {
                    $services[] = $text;
                }
            });
        } catch (\Throwable) {
        }

        // Extract from h2/h3 that look like service names
        try {
            $crawler->filter('h2, h3')->each(function (Crawler $node) use (&$services) {
                $text = trim($node->text(''));
                // Service headings tend to be short and noun-based
                if (strlen($text) > 3 && strlen($text) < 80
                    && ! $this->isInPageChrome($node)
                    && ! $this->isBoilerplate($text)) {
                    $services[] = $text;
                }
            });
        } catch (\Throwable) {
        }

        // Deduplicate first, then limit — slicing first throws away distinct
        // entries to make room for duplicates.
        return array_slice(array_values(array_unique($services)), 0, 20);
    }

    /**
     * Is this node inside site chrome (nav, header, footer, sidebar, cookie banner)?
     * Those regions are full of link lists that are not services.
     */
    private function isInPageChrome(Crawler $node): bool
    {
        $el = $node->getNode(0);

        while ($el instanceof \DOMNode) {
            if ($el instanceof \DOMElement) {
                if (in_array(strtolower($el->tagName), ['nav', 'header', 'footer', 'aside'], true)) {
                    return true;
                }

                $attrs = strtolower($el->getAttribute('class').' '.$el->getAttribute('id').' '.$el->getAttribute('role'));
                if ($attrs !== '' && preg_match(self::CHROME_ATTR_PATTERN, $attrs)) {
                    return true;
                }
            }
            $el = $el->parentNode;
        }

        return false;
    }

    /**
     * Reject navigation labels, CTAs, review badges, dates and other furniture
     * that is structurally list-shaped but says nothing about the business.
     */
    private function isBoilerplate(string $text): bool
    {
        // Trim ASCII only. trim() works on bytes, so a multi-byte character in the
        // charlist (en/em dash) strips matching bytes out of the middle of other
        // multi-byte characters — "★" is E2 98 85 and "—" is E2 80 94, so a dash in
        // the list decapitates the star, leaving invalid UTF-8 that every /u pattern
        // below then silently fails to match.
        $normalised = strtolower(trim($text, " \t\n\r\0\x0B.,:;-"));

        if ($normalised === '' || preg_match(self::BOILERPLATE_PATTERN, $normalised)) {
            return true;
        }

        // Card and listing furniture: teaser CTAs, and headlines ending in a date
        if (preg_match('/\b(read|learn|find out|see) more\b|\bcontinue reading\b/', $normalised)) {
            return true;
        }
        if (preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\s+\d{1,2},?\s+\d{4}\s*$/', $normalised)) {
            return true;
        }

        // Runs of concatenated nav headings tend to open with a section label
        if (preg_match('/^(frequently asked questions|faqs?|our (blog|news|story)|latest news|news|blog)\b/', $normalised)) {
            return true;
        }

        // Star-rating and review-count badges ("★★★★★ 5.0 from 32 Google reviews")
        if (preg_match('/[★☆»«|]|\b\d+(\.\d+)?\s*(stars?|\/\s*5)\b|\b\d+\s+(google\s+)?reviews?\b/u', $normalised)) {
            return true;
        }

        // Bare dates ("August 26, 2026") and mostly-numeric fragments
        if (preg_match('/^\W*(?:\d{1,2}\W+)?(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\W+\d{1,2}?\W*,?\s*\d{4}\W*$/', $normalised)) {
            return true;
        }
        if (preg_match_all('/\d/', $normalised) > (mb_strlen($normalised) / 2)) {
            return true;
        }

        // Single short word — nav labels are far more common than one-word services
        if (! str_contains($normalised, ' ') && mb_strlen($normalised) < 12) {
            return true;
        }

        return false;
    }

    private function extractKeyPhrases(array $data): array
    {
        // Combine all text sources for phrase extraction
        $text = implode(' ', [
            $data['page_title'] ?? '',
            $data['meta_description'] ?? '',
            implode(' ', $data['h1_headings']),
            implode(' ', $data['h2_headings']),
        ]);

        // Simple extraction: split on common stop words, find noun phrases
        // This is a heuristic — the AI will do deeper extraction
        $phrases = [];
        $sentences = preg_split('/[.!?|]/', $text);
        foreach (($sentences ?: []) as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 5 && strlen($sentence) < 80 && ! $this->isBoilerplate($sentence)) {
                $phrases[] = $sentence;
            }
        }

        return array_slice(array_values(array_unique($phrases)), 0, 15);
    }

    private function extractOpeningHours(string $text): array
    {
        $hours = [];
        foreach (self::HOURS_PATTERNS as $pattern) {
            preg_match_all($pattern, $text, $matches);
            if (! empty($matches[0])) {
                $hours = array_merge($hours, $matches[0]);
            }
        }
        return array_unique($hours);
    }

    private function extractPhoneNumbers(string $text): array
    {
        preg_match_all(self::PHONE_PATTERN, $text, $matches);
        return array_unique(array_slice($matches[0] ?? [], 0, 5));
    }

    private function extractEmailAddresses(string $text): array
    {
        preg_match_all(self::EMAIL_PATTERN, $text, $matches);
        // Filter out common non-business emails
        $filtered = array_filter($matches[0] ?? [], fn ($e) =>
            ! str_contains($e, 'example.com') &&
            ! str_contains($e, 'sentry') &&
            ! str_contains($e, 'wordpress')
        );
        return array_unique(array_slice(array_values($filtered), 0, 5));
    }

    private function extractLocationMentions(string $text): array
    {
        // Look for UK city/town mentions in the text
        // This is a best-effort heuristic
        $ukLocations = [
            'London', 'Manchester', 'Birmingham', 'Leeds', 'Glasgow', 'Sheffield',
            'Bradford', 'Edinburgh', 'Liverpool', 'Bristol', 'Cardiff', 'Coventry',
            'Nottingham', 'Leicester', 'Sunderland', 'Belfast', 'Newcastle',
            'Mansfield', 'Derby', 'Stoke', 'Southampton', 'Portsmouth', 'Brighton',
            'Reading', 'Kingston', 'Wolverhampton', 'Plymouth', 'Exeter', 'Oxford',
            'Cambridge', 'York', 'Harrogate', 'Bath', 'Gloucester', 'Cheltenham',
        ];

        $found = [];
        foreach ($ukLocations as $location) {
            if (str_contains($text, $location)) {
                $found[] = $location;
            }
        }

        return $found;
    }

    /**
     * Discover links to /about, /services, /contact pages.
     */
    private function discoverAdditionalPages(Crawler $crawler, string $baseUrl): array
    {
        $interestingPaths = ['/about', '/about-us', '/services', '/our-services', '/what-we-do'];
        $links = [];

        try {
            $parsedBase = parse_url($baseUrl);
            $baseOrigin = ($parsedBase['scheme'] ?? 'https').'://'.($parsedBase['host'] ?? '');

            $crawler->filter('a[href]')->each(function (Crawler $node) use ($baseOrigin, $interestingPaths, &$links) {
                $href = $node->attr('href') ?? '';
                foreach ($interestingPaths as $path) {
                    if (str_contains($href, $path)) {
                        if (str_starts_with($href, 'http')) {
                            $links[] = $href;
                        } elseif (str_starts_with($href, '/')) {
                            $links[] = $baseOrigin.$href;
                        }
                    }
                }
            });
        } catch (\Throwable) {
        }

        return array_unique($links);
    }

    private function scrapeAdditionalPage(string $url): ?array
    {
        try {
            $response = $this->httpClient->get($url);
            $html = (string) $response->getBody();
            $crawler = new Crawler($html);

            return [
                'page_title' => $this->extractTitle($crawler),
                'services' => $this->extractServices($crawler, $crawler->filter('body')->text('')),
                'body_text' => $this->extractBodyText($crawler),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function normaliseUrl(string $url): string
    {
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'https://'.$url;
        }
        return rtrim($url, '/');
    }

    private function trimBodyText(string $text, int $maxChars): string
    {
        if (strlen($text) <= $maxChars) {
            return $text;
        }
        return substr($text, 0, $maxChars).'...';
    }

    private function emptyResult(string $url): array
    {
        return [
            'url' => $url,
            'scraped_at' => now()->toIso8601String(),
            'page_title' => null,
            'meta_description' => null,
            'h1_headings' => [],
            'h2_headings' => [],
            'body_text' => '',
            'services' => [],
            'key_phrases' => [],
            'opening_hours' => [],
            'phone_numbers' => [],
            'email_addresses' => [],
            'location_mentions' => [],
            'page_text' => [],
        ];
    }
}
