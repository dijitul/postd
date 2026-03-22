<?php

namespace App\Modules\Social\Contracts;

use App\Models\SocialConnection;

interface SocialPlatformInterface
{
    /**
     * Publish a post to the platform.
     *
     * @param  SocialConnection  $connection
     * @param  string  $content  The post text content
     * @param  array  $mediaUrls  Array of media URLs (images, videos)
     * @return array{platform_post_id: string, post_url: string|null, status_code: int}
     *
     * @throws \RuntimeException on failure
     */
    public function publishPost(SocialConnection $connection, string $content, array $mediaUrls = []): array;

    /**
     * Validate that the token in the connection is still valid.
     */
    public function validateToken(SocialConnection $connection): bool;

    /**
     * Attempt to refresh the access token using the refresh token.
     *
     * @return array{access_token: string, refresh_token: string|null, expires_at: \DateTimeInterface|null}
     *
     * @throws \RuntimeException if refresh is not supported or fails
     */
    public function refreshToken(SocialConnection $connection): array;

    /**
     * Get the list of accounts/pages available under this connection.
     *
     * @return array<array{id: string, name: string, type: string, url: string|null}>
     */
    public function getAccounts(SocialConnection $connection): array;
}
