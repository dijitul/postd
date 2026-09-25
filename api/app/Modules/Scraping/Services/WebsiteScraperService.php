<?php

namespace App\Modules\Scraping\Services;

use App\Modules\Media\Services\ImageCandidateExtractor;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class WebsiteScraperService
{
    private readonly Client $httpClient;

    private readonly ImageCandidateExtractor $imageExtractor;

    // Photo candidates handed to the image library per scrape. The library
    // only fetches 15 new ones a run, so this is plenty to choose from.
    private const MAX_IMAGE_CANDIDATES = 150;

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

    // Pages read beyond the homepage, and how many of those are kept for articles.
    private const MAX_EXTRA_PAGES = 10;
    private const ARTICLE_PAGES = 4;

    // Stop starting new page fetches after this long, so the job's own timeout
    // is never the thing that ends a scrape of a slow site.
    private const CRAWL_BUDGET_SECONDS = 60;

    private const PHONE_PATTERN = '/(?:\+44|0)[\s\-]?\d{2,4}[\s\-]?\d{3,4}[\s\-]?\d{3,4}/';
    private const EMAIL_PATTERN = '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/';

    /**
     * @param  array<string, mixed>  $clientOptions  Merged over the defaults; tests pass a mock handler here.
     */
    public function __construct(array $clientOptions = [])
    {
        $this->httpClient = new Client($clientOptions + [
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

        $this->imageExtractor = new ImageCandidateExtractor();
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
     *   image_candidates: array<int, array{url: string, page_url: string}>,
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

        $data['page_text'][0]['kind'] = 'home';
        $data['page_text'][0]['lastmod'] = null;

        // Photos on each page, for the image library. Only the URLs leave here:
        // the HTML is parsed while we already have it and never stored.
        $imagesByPage = [$this->imageExtractor->extract($html, $url)];

        // Then the rest of the site that says something about the business:
        // services, about, FAQs and recent articles. Only the homepage and two
        // fixed paths used to be read, so every post drew on the same three
        // pages and a new blog post or service never reached the content at all.
        $startedAt = microtime(true);

        foreach ($this->discoverAdditionalPages($crawler, $url) as $page) {
            if ((microtime(true) - $startedAt) > self::CRAWL_BUDGET_SECONDS) {
                break;
            }

            $additionalData = $this->scrapeAdditionalPage($page['url']);
            if ($additionalData) {
                $data['services'] = array_values(array_unique(array_merge($data['services'], $additionalData['services'])));
                $data['body_text'] .= ' ' . $additionalData['body_text'];
                $imagesByPage[] = $additionalData['image_candidates'];

                if (trim($additionalData['body_text']) !== '') {
                    $data['page_text'][] = [
                        'url'     => $page['url'],
                        'title'   => $additionalData['page_title'],
                        'text'    => $this->trimBodyText($additionalData['body_text'], self::MAX_PAGE_TEXT_CHARS),
                        'kind'    => $page['kind'],
                        'lastmod' => $page['lastmod'],
                    ];
                }
            }
        }

        $data['services'] = array_slice($data['services'], 0, 30);
        $data['image_candidates'] = $this->interleaveImageCandidates($imagesByPage);

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
                // Skip site chrome. This used to call closest() inside a try and
                // treat "no exception" as a match, but closest() returns null when
                // nothing matches and only throws on an empty node list. So the
                // return fired for every node and this method has been handing back
                // an empty string for every site it has ever scraped.
                if ($this->isInPageChrome($node)) {
                    return;
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
     * Pages beyond the homepage worth reading, best first.
     *
     * Candidates come from the sitemap, which is where a new article or service
     * page shows up first, and from the homepage's own links. Pages that tell us
     * what the business does (services, about, FAQs) are always wanted; articles
     * are taken newest first, so fresh material reaches the posts.
     *
     * @return array<int, array{url: string, kind: string, lastmod: string|null}>
     */
    private function discoverAdditionalPages(Crawler $crawler, string $baseUrl): array
    {
        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        $candidates = $this->sitemapEntries($baseUrl);

        try {
            $crawler->filter('a[href]')->each(function (Crawler $node) use ($baseUrl, &$candidates) {
                $absolute = $this->absoluteUrl((string) $node->attr('href'), $baseUrl);

                if ($absolute && ! array_key_exists($absolute, $candidates)) {
                    $candidates[$absolute] = null;
                }
            });
        } catch (\Throwable) {
        }

        $pages = [];
        $home = rtrim($baseUrl, '/');

        foreach ($candidates as $candidate => $lastmod) {
            $candidate = rtrim(strtok((string) $candidate, '#?') ?: (string) $candidate, '/');

            if ($candidate === $home || isset($pages[$candidate]) || ! $this->isContentPage($candidate, $host)) {
                continue;
            }

            $pages[$candidate] = [
                'url'     => $candidate,
                'kind'    => $this->pageKind($candidate),
                'lastmod' => $lastmod,
            ];
        }

        $rank = ['service' => 0, 'about' => 1, 'faq' => 2, 'article' => 3, 'other' => 4];
        $pages = array_values($pages);

        usort($pages, function (array $a, array $b) use ($rank) {
            $byKind = $rank[$a['kind']] <=> $rank[$b['kind']];

            // Newest first within a kind; undated pages after dated ones.
            return $byKind !== 0 ? $byKind : strcmp((string) $b['lastmod'], (string) $a['lastmod']);
        });

        // Hold places for articles, or a site with a long services list would
        // never have its blog read at all.
        $articles = array_values(array_filter($pages, fn ($p) => $p['kind'] === 'article'));
        $rest = array_values(array_filter($pages, fn ($p) => $p['kind'] !== 'article'));
        $articleSlots = min(self::ARTICLE_PAGES, count($articles));

        return array_merge(
            array_slice($rest, 0, self::MAX_EXTRA_PAGES - $articleSlots),
            array_slice($articles, 0, $articleSlots)
        );
    }

    /**
     * URLs and last-modified dates from the site's sitemap, if it has one.
     *
     * Follows one level of sitemap index, which covers WordPress, Yoast, Wix and
     * Squarespace. Anything unreadable just means no sitemap, not a failed scrape.
     *
     * @return array<string, string|null>
     */
    private function sitemapEntries(string $baseUrl): array
    {
        $origin = $this->origin($baseUrl);
        $entries = [];

        foreach (['/sitemap.xml', '/sitemap_index.xml', '/wp-sitemap.xml'] as $path) {
            $xml = $this->fetchXml($origin.$path);

            if (! $xml) {
                continue;
            }

            if ($xml->getName() === 'sitemapindex') {
                $children = 0;

                foreach ($xml->children() as $child) {
                    // Post and page sitemaps hold content; tag, category and author ones do not.
                    $loc = trim((string) $child->loc);
                    if ($loc === '' || preg_match('/(tag|category|author|product_cat|attachment)/i', $loc)) {
                        continue;
                    }

                    if ($childXml = $this->fetchXml($loc)) {
                        $entries += $this->urlsetEntries($childXml);
                    }

                    if (++$children >= 4) {
                        break;
                    }
                }
            } else {
                $entries += $this->urlsetEntries($xml);
            }

            if ($entries !== []) {
                break;
            }
        }

        return array_slice($entries, 0, 300, true);
    }

    /** @return array<string, string|null> */
    private function urlsetEntries(\SimpleXMLElement $xml): array
    {
        $entries = [];

        foreach ($xml->children() as $url) {
            $loc = trim((string) $url->loc);

            if ($loc !== '') {
                $lastmod = trim((string) $url->lastmod);
                $entries[$loc] = $lastmod !== '' ? substr($lastmod, 0, 10) : null;
            }
        }

        return $entries;
    }

    private function fetchXml(string $url): ?\SimpleXMLElement
    {
        try {
            $body = (string) $this->httpClient->get($url, ['timeout' => 8])->getBody();
        } catch (\Throwable) {
            return null;
        }

        if (! str_contains($body, '<urlset') && ! str_contains($body, '<sitemapindex')) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml ?: null;
    }

    /** Is this a same-site HTML page that could say something about the business? */
    private function isContentPage(string $url, string $host): bool
    {
        $parts = parse_url($url);
        $urlHost = strtolower($parts['host'] ?? '');

        // www and the bare domain are the same site.
        if (preg_replace('/^www\./', '', $urlHost) !== preg_replace('/^www\./', '', $host)) {
            return false;
        }

        $path = strtolower($parts['path'] ?? '/');

        if (preg_match('/\.(pdf|jpe?g|png|gif|webp|svg|zip|docx?|xlsx?|mp4|mp3|xml|txt|css|js)$/', $path)) {
            return false;
        }

        return ! preg_match(
            '#/(contact|contact-us|privacy|privacy-policy|cookies?|cookie-policy|terms|legal|accessibility|login|log-in|sign-?in|register|account|my-account|cart|basket|checkout|wp-admin|wp-login|feed|tag|category|author|search|page/\d+)(/|$)#',
            $path
        );
    }

    private function pageKind(string $url): string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return match (true) {
            (bool) preg_match('#(faq|questions)#', $path) => 'faq',
            (bool) preg_match('#(about|our-story|who-we-are|meet-the-team|our-team)#', $path) => 'about',
            (bool) preg_match('#(blog|news|article|insight|guide|advice|tips|case-stud|project|stories|/\d{4}/)#', $path) => 'article',
            (bool) preg_match('#(service|what-we-do|treatment|menu|pricing|prices|products?|solutions|repairs?|install)#', $path) => 'service',
            default => 'other',
        };
    }

    private function absoluteUrl(string $href, string $baseUrl): ?string
    {
        $href = trim($href);

        if ($href === '' || str_starts_with($href, '#') || preg_match('/^(mailto|tel|javascript|sms|whatsapp):/i', $href)) {
            return null;
        }

        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            return (parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https').':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $this->origin($baseUrl).$href;
        }

        return rtrim($baseUrl, '/').'/'.$href;
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
    }

    private function scrapeAdditionalPage(string $url): ?array
    {
        try {
            $response = $this->httpClient->get($url, ['timeout' => 8]);
            $html = (string) $response->getBody();
            $crawler = new Crawler($html);

            return [
                'page_title' => $this->extractTitle($crawler),
                'services' => $this->extractServices($crawler, $crawler->filter('body')->text('')),
                'body_text' => $this->extractBodyText($crawler),
                'image_candidates' => $this->imageExtractor->extract($html, $url),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * One list of photo candidates, taking each page's best in turn.
     *
     * Page by page, a homepage gallery would fill the library's 15 a run on its
     * own and the services and about pages would never get a look in. Taking the
     * first from every page, then the second, spreads the photos across the site
     * so more posts can be matched to a photo from their own page.
     *
     * @param  array<int, array<int, array{url: string, page_url: string}>>  $imagesByPage
     * @return array<int, array{url: string, page_url: string}>
     */
    private function interleaveImageCandidates(array $imagesByPage): array
    {
        $merged = [];

        for ($depth = 0; count($merged) < self::MAX_IMAGE_CANDIDATES; $depth++) {
            $foundAtThisDepth = false;

            foreach ($imagesByPage as $pageImages) {
                if (! isset($pageImages[$depth])) {
                    continue;
                }

                $foundAtThisDepth = true;
                $merged[$pageImages[$depth]['url']] ??= $pageImages[$depth];
            }

            if (! $foundAtThisDepth) {
                break;
            }
        }

        return array_slice(array_values($merged), 0, self::MAX_IMAGE_CANDIDATES);
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
            'image_candidates' => [],
        ];
    }
}
