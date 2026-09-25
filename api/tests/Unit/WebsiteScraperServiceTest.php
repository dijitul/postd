<?php

namespace Tests\Unit;

use App\Modules\Scraping\Services\WebsiteScraperService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class WebsiteScraperServiceTest extends TestCase
{
    public function test_extracts_page_title(): void
    {
        $html = '<html><head><title>My Business | Mansfield</title></head><body><p>We sell things.</p></body></html>';

        // This test would typically mock GuzzleHttp
        // We're testing the extraction logic directly
        $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
        $title = $crawler->filter('title')->text('');

        $this->assertEquals('My Business | Mansfield', $title);
    }

    public function test_extracts_meta_description(): void
    {
        $html = '<html><head><meta name="description" content="A great UK business."></head><body></body></html>';

        $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
        $meta = $crawler->filter('meta[name="description"]');

        $this->assertEquals('A great UK business.', $meta->attr('content'));
    }

    public function test_extracts_headings(): void
    {
        $html = '<html><body><h1>Our Services</h1><h2>Plumbing</h2><h2>Heating</h2></body></html>';
        $crawler = new \Symfony\Component\DomCrawler\Crawler($html);

        $h2s = [];
        $crawler->filter('h2')->each(function ($node) use (&$h2s) {
            $h2s[] = trim($node->text(''));
        });

        $this->assertContains('Plumbing', $h2s);
        $this->assertContains('Heating', $h2s);
    }

    public function test_reads_sitemap_pages_and_skips_legal_and_off_site_links(): void
    {
        $page = fn (string $title, string $body) => new Response(200, [], "<html><head><title>{$title}</title></head><body><main><p>{$body}</p></main></body></html>");

        $routes = [
            'https://smith.test' => new Response(200, [], '<html><head><title>Smith Plumbing</title></head><body><main>'
                .'<p>We fix boilers and bathrooms across Mansfield every single day of the week.</p>'
                .'<a href="/privacy-policy">Privacy</a><a href="https://facebook.com/smith">Facebook</a>'
                .'<a href="/contact">Contact</a><a href="/faq">FAQ</a></main></body></html>'),
            'https://smith.test/sitemap.xml' => new Response(200, [], '<?xml version="1.0"?><urlset>'
                .'<url><loc>https://smith.test/</loc></url>'
                .'<url><loc>https://smith.test/services/boiler-servicing</loc></url>'
                .'<url><loc>https://smith.test/blog/old-post</loc><lastmod>2025-01-01</lastmod></url>'
                .'<url><loc>https://smith.test/blog/new-post</loc><lastmod>2026-09-20T10:00:00+00:00</lastmod></url>'
                .'<url><loc>https://smith.test/terms</loc></url></urlset>'),
            'https://smith.test/services/boiler-servicing' => $page('Boiler servicing', 'An annual boiler service keeps your warranty valid and your family safe.'),
            'https://smith.test/faq' => $page('FAQ', 'Yes, we are Gas Safe registered and happy to show our card on arrival.'),
            'https://smith.test/blog/new-post' => $page('Why your radiator is cold at the top', 'A radiator that is cold at the top usually just needs bleeding.'),
            'https://smith.test/blog/old-post' => $page('An older article', 'This is an older article about choosing a new bathroom suite.'),
        ];

        $requested = [];
        $handler = function ($request) use ($routes, &$requested) {
            $url = rtrim((string) $request->getUri(), '/');
            $requested[] = $url;

            return \GuzzleHttp\Promise\Create::promiseFor($routes[$url] ?? new Response(404));
        };

        $scraper = new WebsiteScraperService(['handler' => HandlerStack::create($handler)]);
        $data = $scraper->scrape('https://smith.test');

        $urls = array_column($data['page_text'], 'url');
        $kinds = array_column($data['page_text'], 'kind', 'url');

        $this->assertSame('https://smith.test', $urls[0]);
        $this->assertSame('service', $kinds['https://smith.test/services/boiler-servicing']);
        $this->assertSame('faq', $kinds['https://smith.test/faq']);
        $this->assertSame('article', $kinds['https://smith.test/blog/new-post']);

        // Newest article is read before the older one.
        $this->assertLessThan(
            array_search('https://smith.test/blog/old-post', $urls, true),
            array_search('https://smith.test/blog/new-post', $urls, true)
        );

        foreach (['privacy-policy', 'contact', 'terms', 'facebook.com'] as $never) {
            $this->assertEmpty(array_filter($requested, fn ($u) => str_contains($u, $never)), "{$never} should not be fetched");
        }
    }
}
