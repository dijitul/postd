<?php

namespace App\Modules\Media\Services;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Finds the photos on a web page worth offering to the image library.
 *
 * Pure parsing, no network: it takes a page's HTML and returns absolute image
 * URLs, each tagged with the page it came from. Downloading and the size and
 * shape checks happen later in ImageLibraryService, so this only has to be
 * cheap and err towards leaving junk out. A logo that slips through is usually
 * caught by the dimension rules anyway; a real photo lost here is lost for good,
 * which is why the keyword matching below is careful about word boundaries.
 */
class ImageCandidateExtractor
{
    /** More than this from one page is a gallery, and the first ones will do. */
    private const MAX_PER_PAGE = 40;

    private const SKIP_EXTENSIONS = ['svg', 'gif', 'ico', 'bmp', 'tif', 'tiff', 'cur'];

    /**
     * Words that mark an image as furniture rather than a photo of the business.
     *
     * Matched as whole tokens (or with a plural or number stuck on), because
     * several are short enough to sit inside real words: "star" in "start",
     * "map" in "maple", "flag" in "flagstone", "loader" in "frontloader".
     */
    private const EXACT_WORDS = [
        'icon', 'star', 'rating', 'flag', 'map', 'qr', 'visa', 'loader', 'badge', 'emoji', 'pixel',
    ];

    /**
     * Words distinctive enough to match at either end of a token too, so
     * "sitelogo", "logowhite" and "paypalbutton" are all caught.
     */
    private const AFFIX_WORDS = [
        'logo', 'favicon', 'sprite', 'avatar', 'placeholder', 'spinner', 'mastercard', 'paypal',
        'trustpilot', 'checkatrade', 'gravatar', 'payment',
    ];

    /** Multi-word markers, matched on the hyphenated form of the text. */
    private const PHRASE_PATTERN = '/(^|-)(banner-?ads?|google-?reviews?|ad-?banner|trust-?badges?|card-?logos?|star-?ratings?)(-|$)/';

    /**
     * class/id values marking site chrome, for images.
     *
     * Deliberately narrower than the text scraper's CHROME_ATTR_PATTERN: the
     * words "header", "banner", "menu" and "widget" are dropped, because hero
     * banners hold the best photo on most small business sites, a restaurant's
     * menu section is full of food photos, and Elementor tags every single
     * element on the page as a widget.
     */
    private const CHROME_ATTR_PATTERN = '/\b(nav|navbar|navigation|breadcrumbs?|footer|sidebar|cookie|consent|social|share|sharing|pagination|offcanvas|drawer|topbar|top-bar|masthead|site-header|site-logo|custom-logo|payment-icons?)\b/';

    /**
     * Third-party hosts that serve a site's own media: site builders, image CDNs
     * and cloud storage. Anything else off-site (ad networks, review widgets,
     * social embeds, stock libraries hotlinked from elsewhere) is left out.
     */
    private const MEDIA_HOST_PATTERN = '/(^|\.)(wp\.com|wixstatic\.com|squarespace-cdn\.com|squarespace\.com|cloudinary\.com|cdn\.shopify\.com|shopifycdn\.com|imgix\.net|ctfassets\.net|cloudfront\.net|amazonaws\.com|digitaloceanspaces\.com|wsimg\.com|website-files\.com|webflow\.com|jimcdn\.com|strikinglycdn\.com|cdn-website\.com|zyrosite\.com|editmysite\.com|weebly\.com|framerusercontent\.com|bigcommerce\.com|sitecdn\.net|b-cdn\.net|kinstacdn\.com|wpengine\.com|wpenginepowered\.com|storyblok\.com|sanity\.io|prismic\.io|ucarecdn\.com|twic\.pics|imagekit\.io|googleusercontent\.com)$/';

    /**
     * @return array<int, array{url: string, page_url: string}>
     */
    public function extract(string $html, string $pageUrl): array
    {
        if (trim($html) === '') {
            return [];
        }

        try {
            $crawler = new Crawler($html, $pageUrl);
        } catch (\Throwable) {
            return [];
        }

        $siteHost = $this->bareHost((string) parse_url($pageUrl, PHP_URL_HOST));
        $baseUrl = $this->baseHref($crawler) ?? $pageUrl;
        $found = [];

        $add = function (?string $raw, string $context = '') use (&$found, $baseUrl, $siteHost, $pageUrl) {
            if (count($found) >= self::MAX_PER_PAGE) {
                return;
            }

            $url = $this->absoluteUrl((string) $raw, $baseUrl);

            if ($url === null || isset($found[$url]) || ! $this->isWanted($url, $context, $siteHost)) {
                return;
            }

            $found[$url] = ['url' => $url, 'page_url' => $pageUrl];
        };

        // The page's own choice of share image comes first. It is usually the
        // hero photo, and it is the one the owner picked to represent the page.
        foreach (['meta[property="og:image"]', 'meta[property="og:image:url"]', 'meta[name="twitter:image"]', 'meta[property="twitter:image"]'] as $selector) {
            $this->each($crawler, $selector, fn (Crawler $node) => $add($node->attr('content')));
        }

        // Gallery links: a thumbnail wrapping a link to the full size photo. The
        // link is the better copy, and the thumbnail alone often fails the size rules.
        $this->each($crawler, 'a[href]', function (Crawler $node) use ($add) {
            $href = (string) $node->attr('href');

            if (! preg_match('/\.(jpe?g|png|webp|avif)(\?|#|$)/i', $href) || $this->isInChrome($node)) {
                return;
            }

            $add($href, $this->describe($node).' '.$this->describeChildren($node));
        });

        $this->each($crawler, 'img, picture source', function (Crawler $node) use ($add) {
            if ($this->isInChrome($node) || $this->isTrackingPixel($node) || $this->isGalleryThumbnail($node)) {
                return;
            }

            $context = $this->describe($node);

            // Lazy loaders keep the real image in a data attribute and put a
            // placeholder in src, so those are read first.
            $srcset = $node->attr('data-srcset') ?? $node->attr('data-lazy-srcset') ?? $node->attr('srcset');
            $best = $srcset ? $this->largestFromSrcset($srcset) : null;

            $src = $best
                ?? $node->attr('data-src')
                ?? $node->attr('data-lazy-src')
                ?? $node->attr('data-original')
                ?? $node->attr('src');

            $add($src, $context);
        });

        // Hero sections set their photo as an inline CSS background.
        $this->each($crawler, '[style*="url("], [data-bg], [data-background-image]', function (Crawler $node) use ($add) {
            if ($this->isInChrome($node)) {
                return;
            }

            $context = $this->describe($node);

            foreach (['data-bg', 'data-background-image'] as $attr) {
                if ($value = $node->attr($attr)) {
                    $add($this->firstCssUrl($value) ?? $value, $context);
                }
            }

            if ($style = $node->attr('style')) {
                if (preg_match_all('/background(?:-image)?\s*:[^;]*?url\(\s*([\'"]?)(.*?)\1\s*\)/i', $style, $matches)) {
                    foreach ($matches[2] as $url) {
                        $add(html_entity_decode($url), $context);
                    }
                }
            }
        });

        return array_values($found);
    }

    /**
     * The biggest image a srcset offers.
     *
     * Parsed by hand rather than split on commas, because image CDN URLs such as
     * Cloudinary's carry commas of their own ("w_400,h_300").
     */
    public function largestFromSrcset(string $srcset): ?string
    {
        $tokens = preg_split('/\s+/', trim($srcset)) ?: [];
        $best = null;
        $bestScore = -1.0;
        $i = 0;

        while ($i < count($tokens)) {
            $url = $tokens[$i++];
            $score = 0.0;

            if ($url === '' || $url === ',') {
                continue;
            }

            if (str_ends_with($url, ',')) {
                // A candidate with no descriptor at all counts as 1x.
                $url = rtrim($url, ',');
                $score = 1.0;
            } elseif (isset($tokens[$i]) && preg_match('/^(\d+(?:\.\d+)?)([wx])(?:,(.*))?$/i', $tokens[$i], $m)) {
                // A width counts for far more than a density, so "800w" beats "2x".
                $score = strtolower($m[2]) === 'w' ? (float) $m[1] : (float) $m[1] * 100;
                $i++;

                // "400w,next.jpg" with no space after the comma.
                if (($m[3] ?? '') !== '') {
                    array_splice($tokens, $i, 0, [$m[3]]);
                }
            } else {
                $score = 1.0;
            }

            if ($score > $bestScore) {
                $best = $url;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /** Should this image URL be offered to the library at all? */
    public function isWanted(string $url, string $context, string $siteHost): bool
    {
        $parts = parse_url($url);
        $path = strtolower(rawurldecode($parts['path'] ?? ''));
        $host = $this->bareHost($parts['host'] ?? '');

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if (in_array($extension, self::SKIP_EXTENSIONS, true)) {
            return false;
        }

        // Some CDNs pick the format from the query string rather than the path.
        if (preg_match('/(^|&)(format|fm|f)=(svg|gif|ico)(&|$)/i', $parts['query'] ?? '')) {
            return false;
        }

        if (! $this->isAllowedHost($host, $siteHost)) {
            return false;
        }

        // The file name and the last few folders say what an image is for
        // ("/images/logos/", "/icons/"), the host and query rarely do.
        if ($this->mentionsJunk($path) || $this->mentionsJunk($context)) {
            return false;
        }

        return true;
    }

    /** Does this text (a path, or an element's alt/class/id) name something that is not a photo? */
    public function mentionsJunk(string $text): bool
    {
        // Split camelCase before lowercasing, so "siteLogo" reads as "site logo".
        $text = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1-$2', $text));
        $hyphenated = trim((string) preg_replace('/[^a-z0-9]+/', '-', $text), '-');

        if ($hyphenated === '') {
            return false;
        }

        if (preg_match(self::PHRASE_PATTERN, $hyphenated)) {
            return true;
        }

        foreach (explode('-', $hyphenated) as $token) {
            // "5star", "icons", "logo2" and "flags" are all the plain word.
            $word = preg_replace('/^\d+|\d+$/', '', $token);
            $singular = preg_replace('/(?<=[a-z]{3})s$/', '', (string) $word);

            if (in_array($word, self::EXACT_WORDS, true) || in_array($singular, self::EXACT_WORDS, true)) {
                return true;
            }

            foreach (self::AFFIX_WORDS as $affix) {
                if (str_starts_with((string) $word, $affix) || str_ends_with((string) $singular, $affix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Same site, a subdomain of it, a known media host, or a CDN host carrying
     * the site's own brand name (cdn.smithplumbing-media.com for smithplumbing.co.uk).
     */
    public function isAllowedHost(string $host, string $siteHost): bool
    {
        if ($host === '' || $siteHost === '') {
            return false;
        }

        if ($host === $siteHost || str_ends_with($host, '.'.$siteHost)) {
            return true;
        }

        if (preg_match(self::MEDIA_HOST_PATTERN, $host)) {
            return true;
        }

        $brand = explode('.', $siteHost)[0];

        return strlen($brand) >= 4 && str_contains($host, $brand);
    }

    /**
     * Is this node inside the site header, navigation, footer or a sidebar?
     *
     * The same walk as WebsiteScraperService::isInPageChrome, with two
     * differences for images: a <header> inside <main> or <article> is a page or
     * post header, which is where the featured photo lives, so it is not chrome;
     * and the class pattern is the narrower one above.
     */
    public function isInChrome(Crawler $node): bool
    {
        $el = $node->getNode(0);
        $inContent = false;
        $ancestors = [];

        while ($el instanceof \DOMNode) {
            if ($el instanceof \DOMElement) {
                $ancestors[] = $el;
                if (in_array(strtolower($el->tagName), ['main', 'article'], true)) {
                    $inContent = true;
                }
            }
            $el = $el->parentNode;
        }

        foreach ($ancestors as $el) {
            $tag = strtolower($el->tagName);

            if (in_array($tag, ['nav', 'footer', 'aside'], true)) {
                return true;
            }

            if ($tag === 'header' && ! $inContent) {
                return true;
            }

            $attrs = strtolower($el->getAttribute('class').' '.$el->getAttribute('id').' '.$el->getAttribute('role'));
            if (trim($attrs) !== '' && preg_match(self::CHROME_ATTR_PATTERN, $attrs)) {
                return true;
            }

            if (in_array(strtolower($el->getAttribute('role')), ['navigation', 'banner', 'contentinfo'], true)) {
                return true;
            }
        }

        return false;
    }

    /** One pixel beacons and other images declared too small to be a photo. */
    private function isTrackingPixel(Crawler $node): bool
    {
        $width = (int) $node->attr('width');
        $height = (int) $node->attr('height');

        return ($width > 0 && $width <= 3) || ($height > 0 && $height <= 3);
    }

    /** A thumbnail whose link already offered the full size copy, so need not be downloaded too. */
    private function isGalleryThumbnail(Crawler $node): bool
    {
        $el = $node->getNode(0)?->parentNode;

        for ($depth = 0; $el instanceof \DOMElement && $depth < 3; $depth++, $el = $el->parentNode) {
            if (strtolower($el->tagName) === 'a') {
                return (bool) preg_match('/\.(jpe?g|png|webp|avif)(\?|#|$)/i', $el->getAttribute('href'));
            }
        }

        return false;
    }

    /** The alt, title, class and id of an element, as one string to scan. */
    private function describe(Crawler $node): string
    {
        return implode(' ', array_filter([
            $node->attr('alt'),
            $node->attr('title'),
            $node->attr('class'),
            $node->attr('id'),
        ]));
    }

    private function describeChildren(Crawler $node): string
    {
        try {
            $img = $node->filter('img');

            return $img->count() ? $this->describe($img->first()) : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function firstCssUrl(string $value): ?string
    {
        return preg_match('/url\(\s*([\'"]?)(.*?)\1\s*\)/i', $value, $m) ? $m[2] : null;
    }

    private function baseHref(Crawler $crawler): ?string
    {
        try {
            $base = $crawler->filter('base[href]');

            return $base->count() ? (string) $base->attr('href') : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function each(Crawler $crawler, string $selector, callable $callback): void
    {
        try {
            $crawler->filter($selector)->each(fn (Crawler $node) => $callback($node));
        } catch (\Throwable) {
            // A selector the parser cannot handle on this page just finds nothing.
        }
    }

    private function bareHost(string $host): string
    {
        return (string) preg_replace('/^www\./', '', strtolower($host));
    }

    /**
     * Resolve an image reference against the page. Anything that is not an
     * http(s) URL at the end (data: URIs, blobs, javascript:) is dropped.
     */
    public function absoluteUrl(string $href, string $baseUrl): ?string
    {
        $href = trim(html_entity_decode($href));

        if ($href === '' || preg_match('/^(data|blob|javascript|about|mailto|tel):/i', $href)) {
            return null;
        }

        $href = (string) strtok($href, '#') ?: $href;
        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $origin = $scheme.'://'.($base['host'] ?? '').(isset($base['port']) ? ':'.$base['port'] : '');

        if (preg_match('#^https?://#i', $href)) {
            $url = $href;
        } elseif (str_starts_with($href, '//')) {
            $url = $scheme.':'.$href;
        } elseif (str_starts_with($href, '/')) {
            $url = $origin.$href;
        } else {
            // Relative to the page's folder, not to the page itself.
            $path = $base['path'] ?? '/';
            $dir = str_ends_with($path, '/') ? $path : (dirname($path) === '\\' ? '/' : rtrim(dirname($path), '/').'/');
            $url = $origin.$this->removeDotSegments($dir.$href);
        }

        // Spaces in hand-written file names are common and break the download.
        $url = str_replace(' ', '%20', $url);

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function removeDotSegments(string $path): string
    {
        [$pathPart, $query] = array_pad(explode('?', $path, 2), 2, null);
        $out = [];

        foreach (explode('/', $pathPart) as $segment) {
            if ($segment === '..') {
                array_pop($out);
            } elseif ($segment !== '.') {
                $out[] = $segment;
            }
        }

        $resolved = implode('/', $out);
        $resolved = str_starts_with($resolved, '/') ? $resolved : '/'.$resolved;

        return $query !== null ? $resolved.'?'.$query : $resolved;
    }
}
