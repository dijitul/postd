<?php

namespace App\Modules\Social\Platforms;

use App\Models\SocialConnection;
use App\Modules\Social\Contracts\SocialPlatformInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * LinkedIn Company Page publishing.
 *
 * Organisation-only by necessity rather than choice. LinkedIn requires the
 * Community Management API to be the ONLY product on a developer app, which rules
 * out both "Sign In with LinkedIn using OpenID Connect" and "Share on LinkedIn" on
 * that same app. Without either we hold no profile scope, /v2/userinfo is closed to
 * us, and posting as a member is impossible — every author here is an organisation
 * URN and there is no personal-profile fallback to reach for.
 *
 * Everything below targets the versioned REST surface (/rest/*) rather than the
 * legacy /v2/ugcPosts and Vector Asset endpoints, which the organisation APIs
 * answer with 426 Upgrade Required.
 */
class LinkedInPlatform implements SocialPlatformInterface
{
    /**
     * The Community Management API scopes we need.
     *
     * Requesting a scope the app was never provisioned makes LinkedIn reject the
     * entire authorisation dialog rather than ignore the one bad entry, so keep
     * this in step with the app's Auth tab.
     */
    public const SCOPES = [
        'r_organization_admin',   // enumerate the Pages the user administers
        'r_organization_social',  // read a Page's existing posts
        'w_organization_social',  // publish to a Page
    ];

    private readonly Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.linkedin.com/',
            'timeout' => 30,
        ]);
    }

    public function publishPost(SocialConnection $connection, string $content, array $mediaUrls = []): array
    {
        $account = $connection->selectedAccount()->first()
            ?? $connection->platformAccounts()->first();

        if (! $account) {
            throw new \RuntimeException(
                'No LinkedIn Company Page found for this connection. You must be an administrator of a LinkedIn Page to post.'
            );
        }

        $author = "urn:li:organization:{$account->platform_account_id}";

        $payload = [
            'author' => $author,
            'commentary' => $content,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];

        // A failed image upload should not cost us the post — fall through and
        // publish the text on its own.
        if (! empty($mediaUrls)) {
            $imageUrn = $this->uploadImage($mediaUrls[0], $author, $connection->access_token);
            if ($imageUrn) {
                $payload['content'] = ['media' => ['id' => $imageUrn]];
            }
        }

        $response = $this->client->post('rest/posts', [
            'headers' => $this->buildHeaders($connection),
            'json' => $payload,
        ]);

        // The Posts API answers 201 with an empty body — the new post's URN comes
        // back in the x-restli-id header, not the payload.
        $postUrn = $response->getHeaderLine('x-restli-id')
            ?: (json_decode((string) $response->getBody(), true)['id'] ?? null);

        if (! $postUrn) {
            throw new \RuntimeException('LinkedIn API returned no post ID: '.$response->getBody());
        }

        return [
            'platform_post_id' => $postUrn,
            'post_url' => "https://www.linkedin.com/feed/update/{$postUrn}/",
            'status_code' => $response->getStatusCode(),
        ];
    }

    public function validateToken(SocialConnection $connection): bool
    {
        try {
            $response = $this->client->get('rest/organizationAcls', [
                'headers' => $this->buildHeaders($connection),
                'query' => [
                    'q' => 'roleAssignee',
                    'role' => 'ADMINISTRATOR',
                    'state' => 'APPROVED',
                ],
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Throwable $e) {
            Log::warning('LinkedInPlatform: Token validation failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function refreshToken(SocialConnection $connection): array
    {
        // Programmatic refresh is a LinkedIn partner privilege. A standard app is
        // handed a 60 day access token and no refresh token at all, and there is no
        // exchange-the-still-valid-token trick like Facebook's, so the user has to
        // reconnect. RefreshSocialTokensCommand warns them before the lapse.
        if (! $connection->refresh_token) {
            throw new \RuntimeException(
                'LinkedIn issues no refresh token to this app. The user must reconnect their LinkedIn account.'
            );
        }

        $response = $this->client->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $connection->refresh_token,
                'client_id' => config('services.linkedin.client_id'),
                'client_secret' => config('services.linkedin.client_secret'),
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);

        if (! isset($data['access_token'])) {
            throw new \RuntimeException('LinkedIn token refresh failed: '.json_encode($data));
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $connection->refresh_token,
            'expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : now()->addDays(60),
        ];
    }

    /**
     * The Company Pages this user administers.
     *
     * Returns an empty array when the user administers none, which is a normal
     * outcome rather than an error — the caller decides how to surface it.
     */
    public function getAccounts(SocialConnection $connection): array
    {
        try {
            $response = $this->client->get('rest/organizationAcls', [
                'headers' => $this->buildHeaders($connection),
                'query' => [
                    'q' => 'roleAssignee',
                    'role' => 'ADMINISTRATOR',
                    'state' => 'APPROVED',
                    'projection' => '(elements*(*,organization~(id,localizedName,vanityName,logoV2(original~:playableStreams))))',
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            $accounts = [];

            foreach ($data['elements'] ?? [] as $element) {
                // The undecorated field carries the URN we actually post with. The
                // decorated organization~ object only holds display detail, and
                // LinkedIn drops it entirely if the projection is not honoured.
                $urn = $element['organization'] ?? '';
                $id = str_replace('urn:li:organization:', '', $urn);

                if ($id === '') {
                    continue;
                }

                $org = $element['organization~'] ?? [];
                $vanityName = $org['vanityName'] ?? null;

                $accounts[] = [
                    'id' => $id,
                    'name' => $org['localizedName'] ?? "Company Page {$id}",
                    'type' => 'organisation',
                    'url' => $vanityName ? "https://www.linkedin.com/company/{$vanityName}/" : null,
                    'metadata' => ['avatar_url' => $this->extractLogoUrl($org)],
                ];
            }

            return $accounts;
        } catch (\Throwable $e) {
            Log::error('LinkedInPlatform: getAccounts failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function buildHeaders(SocialConnection $connection): array
    {
        return [
            'Authorization' => "Bearer {$connection->access_token}",
            'Content-Type' => 'application/json',
            'X-Restli-Protocol-Version' => '2.0.0',
            'LinkedIn-Version' => config('services.linkedin.version'),
        ];
    }

    /**
     * Dig the Page logo out of LinkedIn's decorated logoV2 structure.
     */
    private function extractLogoUrl(array $org): ?string
    {
        return $org['logoV2']['original~']['elements'][0]['identifiers'][0]['identifier'] ?? null;
    }

    /**
     * Upload an image and return its urn:li:image URN, or null on any failure.
     */
    private function uploadImage(string $imageUrl, string $author, string $token): ?string
    {
        try {
            $headers = [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0',
                'LinkedIn-Version' => config('services.linkedin.version'),
            ];

            // Step 1: initialise the upload and claim an image URN
            $initResponse = $this->client->post('rest/images?action=initializeUpload', [
                'headers' => $headers,
                'json' => [
                    'initializeUploadRequest' => ['owner' => $author],
                ],
            ]);

            $initData = json_decode((string) $initResponse->getBody(), true);
            $uploadUrl = $initData['value']['uploadUrl'] ?? null;
            $imageUrn = $initData['value']['image'] ?? null;

            if (! $uploadUrl || ! $imageUrn) {
                Log::warning('LinkedInPlatform: Image upload not initialised', ['response' => $initData]);
                return null;
            }

            $imageData = @file_get_contents($imageUrl);
            if ($imageData === false) {
                Log::warning('LinkedInPlatform: Could not fetch image', ['url' => $imageUrl]);
                return null;
            }

            // Step 2: PUT the binary. The upload URL is absolute and pre-signed, so
            // Guzzle bypasses base_uri for it, which is what we want.
            $this->client->put($uploadUrl, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/octet-stream',
                ],
                'body' => $imageData,
            ]);

            return $imageUrn;
        } catch (\Throwable $e) {
            Log::warning('LinkedInPlatform: Image upload failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
