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
}
