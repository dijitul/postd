<?php

namespace Tests\Unit;

use App\Modules\Media\Services\ImageCandidateExtractor;
use PHPUnit\Framework\TestCase;

class ImageCandidateExtractorTest extends TestCase
{
    private const PAGE = 'https://www.smithplumbing.co.uk/services/boilers';

    private function urls(string $html, string $page = self::PAGE): array
    {
        return array_column((new ImageCandidateExtractor())->extract($html, $page), 'url');
    }

    private function fixture(): string
    {
        return <<<'HTML'
<html>
<head>
  <meta property="og:image" content="https://smithplumbing.co.uk/wp-content/uploads/boiler-hero.jpg">
  <meta name="twitter:image" content="/wp-content/uploads/boiler-hero.jpg">
  <link rel="icon" href="/favicon.ico">
</head>
<body>
  <header class="site-header">
    <img src="/wp-content/uploads/van-photo.jpg" alt="Our van">
    <nav><img src="/img/menu-photo.jpg"></nav>
  </header>
  <main>
    <article>
      <header class="entry-header"><img src="/wp-content/uploads/featured-bathroom.jpg" alt="Finished bathroom"></header>
      <img src="/wp-content/uploads/site-logo.png" alt="Smith Plumbing">
      <img class="custom-icon" src="/uploads/tap.jpg">
      <img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" data-src="/uploads/lazy-kitchen.jpg" alt="Kitchen refit">
      <img src="placeholder.png" data-lazy-src="uploads/lazy-two.webp">
      <img src="/uploads/boiler-small.jpg"
           srcset="/uploads/boiler-400.jpg 400w, /uploads/boiler-1600.jpg 1600w, /uploads/boiler-800.jpg 800w">
      <img src="https://res.cloudinary.com/demo/image/upload/w_400,h_300,c_fill/shop.jpg">
      <img src="/uploads/diagram.svg">
      <img src="/uploads/spinner.gif">
      <img src="https://www.facebook.com/tr?id=1&ev=PageView" width="1" height="1">
      <img src="https://widget.trustpilot.com/stars-5.png" alt="5 stars">
      <img src="https://images.stockphotos.example/random-plumber.jpg">
      <img src="/uploads/flagstone-patio.jpg" alt="Flagstone patio we laid">
      <img src="/uploads/payment-cards.png" alt="We accept Visa">
      <div class="hero" style="background-image: url('/uploads/hero-bg.jpg')"></div>
      <a href="/uploads/gallery-full.jpg"><img src="/uploads/gallery-thumb-150x150.jpg"></a>
    </article>
  </main>
  <aside><img src="/uploads/sidebar-offer.jpg"></aside>
  <footer><img src="/uploads/accreditation-gas-safe.jpg"></footer>
</body>
</html>
HTML;
    }

    public function test_finds_real_photos_and_resolves_them(): void
    {
        $urls = $this->urls($this->fixture());

        $this->assertContains('https://smithplumbing.co.uk/wp-content/uploads/boiler-hero.jpg', $urls);
        $this->assertContains('https://www.smithplumbing.co.uk/wp-content/uploads/featured-bathroom.jpg', $urls);
        $this->assertContains('https://www.smithplumbing.co.uk/uploads/lazy-kitchen.jpg', $urls);
        // Relative to the page's folder.
        $this->assertContains('https://www.smithplumbing.co.uk/services/uploads/lazy-two.webp', $urls);
        $this->assertContains('https://www.smithplumbing.co.uk/uploads/hero-bg.jpg', $urls);
        $this->assertContains('https://www.smithplumbing.co.uk/uploads/flagstone-patio.jpg', $urls);
    }

    public function test_og_image_comes_first(): void
    {
        $urls = $this->urls($this->fixture());

        $this->assertSame('https://smithplumbing.co.uk/wp-content/uploads/boiler-hero.jpg', $urls[0]);
    }

    public function test_takes_the_largest_srcset_candidate(): void
    {
        $urls = $this->urls($this->fixture());

        $this->assertContains('https://www.smithplumbing.co.uk/uploads/boiler-1600.jpg', $urls);
        $this->assertNotContains('https://www.smithplumbing.co.uk/uploads/boiler-small.jpg', $urls);
        $this->assertNotContains('https://www.smithplumbing.co.uk/uploads/boiler-400.jpg', $urls);
    }

    public function test_keeps_known_cdn_hosts_but_not_other_third_parties(): void
    {
        $urls = $this->urls($this->fixture());

        $this->assertContains('https://res.cloudinary.com/demo/image/upload/w_400,h_300,c_fill/shop.jpg', $urls);
        $this->assertNotContains('https://images.stockphotos.example/random-plumber.jpg', $urls);
        $this->assertNotContains('https://www.facebook.com/tr?id=1&ev=PageView', $urls);
    }

    public function test_skips_logos_icons_badges_and_vector_or_animated_files(): void
    {
        $urls = implode("\n", $this->urls($this->fixture()));

        foreach (['site-logo', 'tap.jpg', 'diagram.svg', 'spinner.gif', 'stars-5', 'payment-cards', 'favicon', 'data:'] as $junk) {
            $this->assertStringNotContainsString($junk, $urls, "{$junk} should have been skipped");
        }
    }

    public function test_skips_images_in_site_header_nav_sidebar_and_footer(): void
    {
        $urls = implode("\n", $this->urls($this->fixture()));

        $this->assertStringNotContainsString('van-photo', $urls);
        $this->assertStringNotContainsString('menu-photo', $urls);
        $this->assertStringNotContainsString('sidebar-offer', $urls);
        $this->assertStringNotContainsString('accreditation', $urls);
    }

    public function test_prefers_gallery_link_over_its_thumbnail(): void
    {
        $urls = $this->urls($this->fixture());

        $this->assertContains('https://www.smithplumbing.co.uk/uploads/gallery-full.jpg', $urls);
        $this->assertNotContains('https://www.smithplumbing.co.uk/uploads/gallery-thumb-150x150.jpg', $urls);
    }

    public function test_records_the_page_each_image_came_from(): void
    {
        $candidates = (new ImageCandidateExtractor())->extract($this->fixture(), self::PAGE);

        $this->assertNotEmpty($candidates);
        foreach ($candidates as $candidate) {
            $this->assertSame(self::PAGE, $candidate['page_url']);
        }
    }

    public function test_deduplicates_urls(): void
    {
        $html = '<main><img src="/a/photo.jpg"><img src="/a/photo.jpg"><img src="https://smithplumbing.co.uk/a/photo.jpg#x"></main>';

        $this->assertSame(['https://smithplumbing.co.uk/a/photo.jpg'], $this->urls($html, 'https://smithplumbing.co.uk/'));
    }

    public function test_srcset_parsing_handles_commas_inside_urls_and_missing_spaces(): void
    {
        $extractor = new ImageCandidateExtractor();

        $this->assertSame(
            'https://res.cloudinary.com/x/w_1200,h_800/a.jpg',
            $extractor->largestFromSrcset('https://res.cloudinary.com/x/w_400,h_300/a.jpg 400w, https://res.cloudinary.com/x/w_1200,h_800/a.jpg 1200w')
        );
        $this->assertSame('b.jpg', $extractor->largestFromSrcset('a.jpg 400w,b.jpg 900w'));
        $this->assertSame('b.jpg', $extractor->largestFromSrcset('a.jpg 1x, b.jpg 2x'));
    }

    public function test_junk_words_respect_word_boundaries(): void
    {
        $extractor = new ImageCandidateExtractor();

        $this->assertTrue($extractor->mentionsJunk('/images/siteLogo.png'));
        $this->assertTrue($extractor->mentionsJunk('/icons/phone.png'));
        $this->assertTrue($extractor->mentionsJunk('5star-rating.png'));
        $this->assertTrue($extractor->mentionsJunk('google-reviews-badge.jpg'));
        $this->assertTrue($extractor->mentionsJunk('/uploads/uk-flag.jpg'));

        $this->assertFalse($extractor->mentionsJunk('/uploads/start-of-job.jpg'));
        $this->assertFalse($extractor->mentionsJunk('/uploads/maple-worktop.jpg'));
        $this->assertFalse($extractor->mentionsJunk('/uploads/silicone-sealant.jpg'));
        $this->assertFalse($extractor->mentionsJunk('/uploads/frontloader-hire.jpg'));
        $this->assertFalse($extractor->mentionsJunk('/wp-content/uploads/2026/05/IMG_1234.jpg'));
    }

    public function test_host_rules(): void
    {
        $extractor = new ImageCandidateExtractor();

        $this->assertTrue($extractor->isAllowedHost('smithplumbing.co.uk', 'smithplumbing.co.uk'));
        $this->assertTrue($extractor->isAllowedHost('media.smithplumbing.co.uk', 'smithplumbing.co.uk'));
        $this->assertTrue($extractor->isAllowedHost('static.wixstatic.com', 'smithplumbing.co.uk'));
        $this->assertTrue($extractor->isAllowedHost('i0.wp.com', 'smithplumbing.co.uk'));
        $this->assertTrue($extractor->isAllowedHost('cdn.smithplumbing-assets.com', 'smithplumbing.co.uk'));

        $this->assertFalse($extractor->isAllowedHost('images.unsplash.com', 'smithplumbing.co.uk'));
        $this->assertFalse($extractor->isAllowedHost('www.facebook.com', 'smithplumbing.co.uk'));
    }

    public function test_empty_or_broken_html_yields_nothing(): void
    {
        $this->assertSame([], $this->urls(''));
        $this->assertSame([], $this->urls('<html><body><p>No pictures here.</p></body></html>'));
    }
}
