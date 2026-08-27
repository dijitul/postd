<?php

namespace App\Modules\Scraping\Services;

use App\Models\Business;
use App\Models\ContentSource;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class GoogleReviewsService
{
    private readonly Client $httpClient;
    private readonly ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google_places.key', '');
        $this->httpClient = new Client([
            'timeout' => 15,
            'base_uri' => 'https://maps.googleapis.com',
        ]);
    }

    /**
     * Fetch reviews for a business using the Google Places API.
     * Returns an array of review ContentSource records created.
     */
    public function fetchReviews(Business $business): array
    {
        if (! $this->apiKey) {
            Log::warning('GoogleReviewsService: No API key configured.');
            return [];
        }

        // First, resolve the Place ID if we don't have one
        if (! $business->google_place_id) {
            $placeId = $this->resolvePlaceId($business);
            if (! $placeId) {
                return [];
            }
            $business->update(['google_place_id' => $placeId]);
        }

        $reviews = $this->fetchPlaceReviews($business->google_place_id);
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

            $source = ContentSource::create([
                'business_id' => $business->id,
                'type' => ContentSource::TYPE_REVIEW,
                'source_url' => $business->google_reviews_url,
                'raw_data' => $reviewText,
                'structured_data' => [
                    'author' => $review['authorAttribution']['displayName'] ?? 'Anonymous',
                    'rating' => $review['rating'] ?? 0,
                    'publish_time' => $review['publishTime'] ?? null,
                    'original_language' => $review['originalText']['languageCode'] ?? 'en',
                ],
                'sentiment_score' => $sentiment,
                'scraped_at' => now(),
            ]);

            $sources[] = $source;
        }

        Log::info("GoogleReviewsService: Fetched ".count($sources)." usable reviews for business {$business->id}");

        return $sources;
    }

    /**
     * Resolve a Place ID from the Google Reviews URL or business name.
     */
    private function resolvePlaceId(Business $business): ?string
    {
        // Try extracting from the Google reviews URL if it contains a place ID
        if ($business->google_reviews_url) {
            // URLs like https://g.page/r/xxx/review contain the place reference
            if (preg_match('/g\.page\/r\/([A-Za-z0-9_-]+)/', $business->google_reviews_url, $m)) {
                return $m[1];
            }

            // URLs like https://maps.google.com/?cid=xxx
            if (preg_match('/[?&]cid=(\d+)/', $business->google_reviews_url, $m)) {
                return $this->searchPlaceById($m[1]);
            }
        }

        // Fall back to text search
        return $this->searchPlaceByName($business->name, $business->city);
    }

    private function searchPlaceByName(string $name, ?string $city): ?string
    {
        try {
            $query = $name.($city ? ', '.$city : '').', UK';
            $response = $this->httpClient->get('/maps/api/place/findplacefromtext/json', [
                'query' => [
                    'input' => $query,
                    'inputtype' => 'textquery',
                    'fields' => 'place_id',
                    'key' => $this->apiKey,
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            return $data['candidates'][0]['place_id'] ?? null;
        } catch (\Throwable $e) {
            Log::error('GoogleReviewsService: Place search failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function searchPlaceById(string $cid): ?string
    {
        try {
            $response = $this->httpClient->get('/maps/api/place/details/json', [
                'query' => [
                    'cid' => $cid,
                    'fields' => 'place_id',
                    'key' => $this->apiKey,
                ],
            ]);
            $data = json_decode((string) $response->getBody(), true);
            return $data['result']['place_id'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchPlaceReviews(string $placeId): array
    {
        try {
            // Use Places API v1 (new) for better review data
            $response = $this->httpClient->get("/v1/places/{$placeId}", [
                'headers' => [
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => 'reviews,rating,userRatingCount',
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            return $data['reviews'] ?? [];
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
