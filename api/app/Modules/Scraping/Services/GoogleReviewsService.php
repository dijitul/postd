<?php

namespace App\Modules\Scraping\Services;

use App\Models\Business;
use App\Models\ContentSource;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Fetches Google reviews for a business.
 *
 * Everything here talks to **Places API (New)** on places.googleapis.com. The
 * service previously mixed two products: place lookup went to the legacy
 * maps.googleapis.com/maps/api/place endpoints while the review fetch used a
 * New-API path on the legacy host, which cannot answer it. Google also stopped
 * enabling the legacy Places API on projects created after March 2025, so the
 * old half was becoming impossible to switch on regardless. One product, one
 * host, one API to enable in Cloud Console.
 */
class GoogleReviewsService
{
    private readonly Client $httpClient;
    private readonly ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google_places.key', '');
        $this->httpClient = new Client([
            'timeout' => 15,
            'base_uri' => config('services.google_places.base_url'),
        ]);
    }

    /**
     * Fetch reviews for a business.
     * Returns an array of review ContentSource records created.
     */
    public function fetchReviews(Business $business): array
    {
        if (! $this->apiKey) {
            Log::warning('GoogleReviewsService: No API key configured.');
            return [];
        }

        $placeId = $business->google_place_id ?: $this->resolveAndStorePlaceId($business);

        if (! $placeId) {
            return [];
        }

        $reviews = $this->fetchPlaceReviews($placeId);

        // A stored id can be wrong rather than merely unlucky: earlier versions of
        // this service scraped a token out of a g.page URL and saved it as though
        // it were a place id, so some businesses carry one that will never resolve.
        // Clear it and look the place up properly, once.
        if ($reviews === null) {
            $business->update(['google_place_id' => null]);
            $freshId = $this->resolveAndStorePlaceId($business);

            $reviews = $freshId ? $this->fetchPlaceReviews($freshId) : [];
        }

        if (empty($reviews)) {
            return [];
        }

        $sources = [];
        foreach ($reviews as $review) {
            // Only process positive reviews for content (4+ stars)
            if (($review['rating'] ?? 0) < 4) {
                continue;
            }

            $reviewText = $review['text']['text'] ?? '';
            if (strlen($reviewText) < 20) {
                continue; // Too short to be useful
            }

            $sentiment = $this->calculateSentiment($review['rating'] ?? 0);

            // Google returns the same handful of reviews on every fetch, so
            // matching on the review text keeps a daily scrape from stacking up
            // duplicate copies of the same customer saying the same thing.
            $source = ContentSource::updateOrCreate(
                [
                    'business_id' => $business->id,
                    'type' => ContentSource::TYPE_REVIEW,
                    'raw_data' => $reviewText,
                ],
                [
                    'source_url' => $business->google_reviews_url,
                    'structured_data' => [
                        'author' => $review['authorAttribution']['displayName'] ?? 'Anonymous',
                        'rating' => $review['rating'] ?? 0,
                        'publish_time' => $review['publishTime'] ?? null,
                        'original_language' => $review['originalText']['languageCode'] ?? 'en',
                    ],
                    'sentiment_score' => $sentiment,
                    'scraped_at' => now(),
                ]
            );

            $sources[] = $source;
        }

        Log::info("GoogleReviewsService: Fetched ".count($sources)." usable reviews for business {$business->id}");

        return $sources;
    }

    /**
     * Work out this business's place id and remember it.
     */
    private function resolveAndStorePlaceId(Business $business): ?string
    {
        $placeId = $this->placeIdFromUrl($business->google_reviews_url)
            ?? $this->searchPlaceByName($business->name, $business->city);

        if ($placeId) {
            $business->update(['google_place_id' => $placeId]);
        }

        return $placeId;
    }

    /**
     * Pull a place id out of a Google URL, when the URL actually carries one.
     *
     * Only the explicit place_id parameter is trusted. A g.page/r/ token and a
     * maps ?cid= are different identifiers entirely, and Places API (New) has no
     * endpoint that turns either into a place id, so a URL carrying one of those
     * falls through to the name search rather than producing a plausible-looking
     * id that then 404s on every fetch.
     */
    private function placeIdFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (preg_match('/[?&]place_?id=([A-Za-z0-9_-]+)/i', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Text Search (New): find the place id for a business by name.
     */
    private function searchPlaceByName(string $name, ?string $city): ?string
    {
        try {
            $response = $this->httpClient->post('/v1/places:searchText', [
                'headers' => [
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => 'places.id',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'textQuery' => $name.($city ? ', '.$city : '').', UK',
                    'regionCode' => 'GB',
                    'maxResultCount' => 1,
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);

            return $data['places'][0]['id'] ?? null;
        } catch (\Throwable $e) {
            Log::error('GoogleReviewsService: Place search failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Place Details (New): the reviews for one place.
     *
     * Returns null, distinct from an empty array, when the place id itself is not
     * recognised, so the caller can tell "this id is wrong" from "this place has
     * no reviews" and only re-resolve in the first case.
     */
    private function fetchPlaceReviews(string $placeId): ?array
    {
        try {
            $response = $this->httpClient->get("/v1/places/{$placeId}", [
                'headers' => [
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => 'reviews,rating,userRatingCount',
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);

            return $data['reviews'] ?? [];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $status = $e->getResponse()?->getStatusCode();

            Log::error('GoogleReviewsService: Failed to fetch reviews', [
                'place_id' => $placeId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return in_array($status, [400, 404], true) ? null : [];
        } catch (\Throwable $e) {
            Log::error('GoogleReviewsService: Failed to fetch reviews', [
                'place_id' => $placeId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Convert a star rating (1-5) to a sentiment score (-1 to 1).
     */
    private function calculateSentiment(int $rating): float
    {
        return match ($rating) {
            5 => 1.0,
            4 => 0.6,
            3 => 0.0,
            2 => -0.5,
            1 => -1.0,
            default => 0.0,
        };
    }
}
