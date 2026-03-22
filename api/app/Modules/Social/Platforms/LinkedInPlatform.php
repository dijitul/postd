<?php

namespace App\Modules\Social\Platforms;

use App\Models\SocialConnection;
use App\Modules\Social\Contracts\SocialPlatformInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class LinkedInPlatform implements SocialPlatformInterface
{
    private readonly Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.linkedin.com/v2/',
            'timeout' => 30,
        ]);
    }

    public function publishPost(SocialConnection $connection, string $content, array $mediaUrls = []): array
    {
        $account = $connection->selectedAccount()->first();
        $author = $account ? "urn:li:organization:{$account->platform_account_id}" : $this->getPersonUrn($connection);

        $shareContent = [
            'shareCommentary' => ['text' => $content],
            'shareMediaCategory' => 'NONE',
        ];

        if (! empty($mediaUrls)) {
            $asset = $this->uploadImage($mediaUrls[0], $author, $connection->access_token);
            if ($asset) {
                $shareContent['shareMediaCategory'] = 'IMAGE';
                $shareContent['media'] = [[
                    'status' => 'READY',
                    'media' => $asset,
                ]];
            }
        }

        $payload = [
            'author' => $author,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => $shareContent,
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        $response = $this->client->post('ugcPosts', [
            'headers' => $this->buildHeaders($connection),
            'json' => $payload,
        ]);

        $data = json_decode((string) $response->getBody(), true);
        $postId = $data['id'] ?? null;

        if (! $postId) {
            throw new \RuntimeException('LinkedIn API returned no post ID: '.json_encode($data));
        }

        return [
            'platform_post_id' => $postId,
            'post_url' => null, // LinkedIn doesn't return a direct post URL in the API response
            'status_code' => $response->getStatusCode(),
        ];
    }

    public function validateToken(SocialConnection $connection): bool
    {
        try {
            $response = $this->client->get('userinfo', [
                'headers' => $this->buildHeaders($connection),
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    public function refreshToken(SocialConnection $connection): array
    {
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

    public function getAccounts(SocialConnection $connection): array
    {
        try {
            // Get person's own profile
            $meResponse = $this->client->get('userinfo', [
                'headers' => $this->buildHeaders($connection),
            ]);
            $me = json_decode((string) $meResponse->getBody(), true);

            $accounts = [[
                'id' => $me['sub'] ?? '',
                'name' => ($me['given_name'] ?? '').' '.($me['family_name'] ?? ''),
                'type' => 'profile',
                'url' => null,
                'metadata' => ['email' => $me['email'] ?? null],
            ]];

            // Also get organisations where user is an admin
            try {
                $orgResponse = $this->client->get('organizationAcls', [
                    'headers' => $this->buildHeaders($connection),
                    'query' => [
                        'q' => 'roleAssignee',
                        'role' => 'ADMINISTRATOR',
                        'projection' => '(elements*(*,organization~(id,localizedName,logoV2)))',
                    ],
                ]);

                $orgs = json_decode((string) $orgResponse->getBody(), true);
                foreach ($orgs['elements'] ?? [] as $element) {
                    $org = $element['organization~'] ?? [];
                    if ($org) {
                        $accounts[] = [
                            'id' => str_replace('urn:li:organization:', '', $org['id'] ?? ''),
                            'name' => $org['localizedName'] ?? 'Organisation',
                            'type' => 'organisation',
                            'url' => null,
                            'metadata' => [],
                        ];
                    }
                }
            } catch (\Throwable) {
                // No org access — that's fine
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
        ];
    }

    private function getPersonUrn(SocialConnection $connection): string
    {
        $response = $this->client->get('userinfo', [
            'headers' => $this->buildHeaders($connection),
        ]);
        $data = json_decode((string) $response->getBody(), true);
        return "urn:li:person:{$data['sub']}";
    }

    private function uploadImage(string $imageUrl, string $author, string $token): ?string
    {
        try {
            // Step 1: Register the upload
            $registerResponse = $this->client->post('assets?action=registerUpload', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'registerUploadRequest' => [
                        'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                        'owner' => $author,
                        'serviceRelationships' => [[
                            'relationshipType' => 'OWNER',
                            'identifier' => 'urn:li:userGeneratedContent',
                        ]],
                    ],
                ],
            ]);

            $registerData = json_decode((string) $registerResponse->getBody(), true);
            $uploadUrl = $registerData['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? null;
            $asset = $registerData['value']['asset'] ?? null;

            if (! $uploadUrl || ! $asset) {
                return null;
            }

            // Step 2: Upload the image binary
            $imageData = file_get_contents($imageUrl);
            if ($imageData === false) {
                return null;
            }

            $this->client->put($uploadUrl, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/octet-stream',
                ],
                'body' => $imageData,
            ]);

            return $asset;
        } catch (\Throwable $e) {
            Log::warning('LinkedInPlatform: Image upload failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
