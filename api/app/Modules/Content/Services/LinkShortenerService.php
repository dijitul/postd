<?php

namespace App\Modules\Content\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns a long destination into a short link on one of our own domains.
 *
 * Posts carry URLs that have to survive being read off a phone screen, and a
 * Google reviews URL is unreadable at that length. Every link is created fresh
 * per post rather than reused, because click counts on LinkVine are per link:
 * one shared link for the reviews page would tell us the page got clicks but
 * never which post earned them.
 *
 * Nothing here is allowed to stop a post being written. Every failure path
 * returns null and the caller falls back to the original URL, which is longer
 * but works.
 */
class LinkShortenerService
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $baseUrl = null,
        private readonly ?string $domain = null,
    ) {}

    /**
     * Create a short link, or return null if we could not.
     *
     * @return array{id: int|null, short_url: string}|null
     */
    public function shorten(string $destination): ?array
    {
        $apiKey = $this->apiKey ?? config('services.linkvine.key');

        if (! $apiKey) {
            return null;
        }

        // The API rejects a destination with no scheme, and that is a 400 worth
        // never sending: the payload is wrong and would fail identically on retry.
        if (! preg_match('#^https?://#i', $destination)) {
            Log::warning('LinkShortenerService: Destination has no scheme, not shortening', [
                'destination' => $destination,
            ]);

            return null;
        }

        $response = $this->post($apiKey, [
            'location_url' => $destination,
            'domain'       => $this->domain ?? config('services.linkvine.domain'),
        ]);

        if (! $response) {
            return null;
        }

        $shortUrl = $response['data']['short_url'] ?? null;

        if (! is_string($shortUrl) || $shortUrl === '') {
            Log::warning('LinkShortenerService: Created a link with no short_url in the response', [
                'destination' => $destination,
            ]);

            return null;
        }

        return [
            'id'       => $response['data']['id'] ?? null,
            'short_url' => $shortUrl,
        ];
    }

    /**
     * POST to the links endpoint, returning the decoded body on success.
     *
     * Only 429 is worth waiting out. A 400 or 401 means the payload or the key is
     * wrong and will fail the same way every time, and a 404 means the domain is
     * not ours, so none of those are retried.
     *
     * @return array<string, mixed>|null
     */
    private function post(string $apiKey, array $payload): ?array
    {
        $baseUrl = rtrim($this->baseUrl ?? config('services.linkvine.base_url'), '/');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])->timeout(15)->connectTimeout(5)->post($baseUrl.'/links', $payload);
        } catch (\Throwable $e) {
            Log::warning('LinkShortenerService: Request failed', ['error' => $e->getMessage()]);

            return null;
        }

        if ($response->status() === 201) {
            return $response->json();
        }

        Log::warning('LinkShortenerService: Could not create a short link, using the full URL instead', [
            'status'       => $response->status(),
            'error'        => $response->json('errors.0.title') ?? $response->body(),
            // Present once throttled, and the only thing worth knowing on a 429.
            'retry_after_s' => $response->header('X-RateLimit-Reset') ?: null,
        ]);

        return null;
    }
}
