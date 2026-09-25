<?php

namespace App\Modules\Media\Services;

use App\Models\Business;
use App\Models\BusinessImage;
use App\Models\Post;
use App\Modules\Media\Exceptions\ImageRejected;
use App\Modules\Social\Services\SocialConnectionService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The business's own photos, used on posts before any AI image is made.
 *
 * Three ways in:
 *  - website: photos found on the pages ScrapeBusinessJob crawls
 *    (ImageCandidateExtractor finds them, importFromUrls fetches them).
 *  - google: the photos the owner uploaded to their Google Business Profile.
 *  - upload: photos added on the Photos page.
 * AI images are recorded too (source ai) so the owner sees everything that
 * went out, but ImagePicker never chooses them again.
 *
 * Library photos cost nothing to use, so they are not counted against the plan's
 * AI image allowance (that counts AiCostLog rows) and are not switched off by
 * POST_IMAGES_ENABLED, which only governs paying for AI images.
 */
class ImageLibraryService
{
    // Fetched once per scrape, so a big site trickles in over a few weeks
    // rather than a hundred downloads landing on one run.
    public const MAX_NEW_WEBSITE_PER_RUN = 15;

    // A site with more photos than this is mostly a portfolio or a shop, and
    // sixty is already more variety than a year of posts needs. Older ones are
    // never deleted to make room; importing simply stops.
    public const MAX_WEBSITE_TOTAL = 60;

    public const MAX_NEW_GOOGLE_PER_RUN = 30;

    // Uploads are deliberate, so the ceiling is only there to stop abuse.
    public const MAX_UPLOADS_TOTAL = 300;

    public const DOWNLOAD_BUDGET_SECONDS = 40;
    public const DOWNLOAD_TIMEOUT_SECONDS = 8;
    public const MAX_DOWNLOAD_BYTES = 8 * 1024 * 1024;

    // The GBP API allowance is tiny and shared by every customer, so the media
    // list is read at most once a week per business, whatever the outcome.
    public const GOOGLE_SYNC_DAYS = 7;

    // Categories that are the business's logo or profile picture, not a photo
    // of its work. LOGO is an old alias Google now maps to PROFILE.
    private const GOOGLE_SKIP_CATEGORIES = ['LOGO', 'PROFILE'];

    private const GBP_MEDIA_URL = 'https://mybusiness.googleapis.com/v4/';

    private const IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/webp', 'image/avif'];

    // URLs that turned out not to be usable photos, remembered so the weekly
    // scrape does not download the same logo every time.
    private const REJECTED_TTL_DAYS = 30;
    private const REJECTED_MAX = 2000;

    private readonly Client $http;

    /** @var array<string, bool> Hosts already checked for private addresses in this run. */
    private array $hostIsPublic = [];

    /**
     * @param  array<string, mixed>  $clientOptions  Merged over the defaults; tests pass a mock handler here.
     */
    public function __construct(
        private readonly ImageProcessor $processor,
        private readonly ImagePicker $picker,
        private readonly ImageVetter $vetter,
        private readonly SocialConnectionService $connections,
        array $clientOptions = []
    ) {
        $this->http = new Client($clientOptions + [
            'timeout' => self::DOWNLOAD_TIMEOUT_SECONDS,
            'connect_timeout' => 5,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; postd.uk/1.0; +https://postd.uk/bot)',
                'Accept' => 'image/avif,image/webp,image/jpeg,image/png,image/*;q=0.8',
            ],
        ]);
    }

    // ── Importing ──────────────────────────────────────────────────────────

    /**
     * Fetch website photo candidates into the library.
     *
     * @param  array<int, array{url: string, page_url?: string|null}>  $candidates  From ImageCandidateExtractor, best first.
     * @return int  How many new photos were added.
     */
    public function importFromUrls(Business $business, array $candidates, float $budgetSeconds = self::DOWNLOAD_BUDGET_SECONDS): int
    {
        $existing = $business->images()->where('source', BusinessImage::SOURCE_WEBSITE)->count();
        $allowed = min(self::MAX_NEW_WEBSITE_PER_RUN, self::MAX_WEBSITE_TOTAL - $existing);

        if ($allowed <= 0 || $candidates === []) {
            return 0;
        }

        $known = array_fill_keys(
            $business->images()->whereNotNull('source_url')->pluck('source_url')->all(),
            true
        );

        $rejectedKey = "image_library_rejected_{$business->id}";
        $rejected = Cache::get($rejectedKey, []);
        $startedAt = microtime(true);
        $imported = 0;

        foreach ($candidates as $candidate) {
            $url = (string) ($candidate['url'] ?? '');
            $urlKey = sha1($url);

            if ($imported >= $allowed || (microtime(true) - $startedAt) > $budgetSeconds) {
                break;
            }

            if ($url === '' || isset($known[$url]) || isset($rejected[$urlKey])) {
                continue;
            }

            $known[$url] = true;

            try {
                $bytes = $this->download($url);
            } catch (\Throwable) {
                // Timeouts and dropped connections might work next week.
                continue;
            }

            try {
                if ($bytes === null) {
                    throw new ImageRejected('unsupported');
                }

                $image = $this->store($business, $this->processor->process($bytes), [
                    'source' => BusinessImage::SOURCE_WEBSITE,
                    'source_url' => $url,
                    'page_url' => $candidate['page_url'] ?? null,
                ]);

                if ($image) {
                    $imported++;
                }
            } catch (ImageRejected) {
                $rejected[$urlKey] = true;
            } catch (\Throwable $e) {
                Log::warning('ImageLibraryService: Could not import a website photo', [
                    'business_id' => $business->id,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Cache::put($rejectedKey, array_slice($rejected, -self::REJECTED_MAX, null, true), now()->addDays(self::REJECTED_TTL_DAYS));

        Log::info("ImageLibraryService: Imported {$imported} website photos for business {$business->id}");

        return $imported;
    }

    /**
     * When the Google photos will next be read, or null if they are due now.
     */
    public function nextGoogleSyncAt(Business $business): ?\Carbon\CarbonInterface
    {
        $last = $business->images_google_synced_at;

        if (! $last || $last->lessThanOrEqualTo(now()->subDays(self::GOOGLE_SYNC_DAYS))) {
            return null;
        }

        return $last->copy()->addDays(self::GOOGLE_SYNC_DAYS);
    }

    /**
     * Copy the owner's own Google Business Profile photos into the library.
     *
     * Reads accounts.locations.media.list (v4), which returns the merchant's
     * media only; customer photos live under a separate customers endpoint that
     * is deliberately not used, since a customer's photo is not ours to post.
     * Everything here is quiet on failure: the GBP API is still allowlisted for
     * very few projects, and 403 and 429 are the normal answers until it is.
     *
     * @return int  How many new photos were added.
     */
    public function importFromGoogle(Business $business): int
    {
        if ($this->nextGoogleSyncAt($business) !== null) {
            return 0;
        }

        $connection = $business->getConnectionForPlatform('google_business_profile');
        $location = $connection?->selectedAccount()->first()?->platform_account_id;

        // Only a location the owner picked. Guessing between several would risk
        // pulling another client's photos into an agency user's business.
        if (! $connection || ! $location || ! preg_match('#^accounts/[^/]+/locations/[^/]+$#', $location)) {
            return 0;
        }

        // Recorded before the call, so a failure also waits the full week.
        $business->forceFill(['images_google_synced_at' => now()])->save();

        try {
            if ($connection->isExpired()) {
                $connection = $this->connections->refreshToken($connection);
            }

            $response = $this->http->get(self::GBP_MEDIA_URL.$location.'/media', [
                'timeout' => 15,
                'query' => ['pageSize' => 100],
                'headers' => [
                    'Authorization' => "Bearer {$connection->access_token}",
                    'Accept' => 'application/json',
                ],
            ]);

            $items = json_decode((string) $response->getBody(), true)['mediaItems'] ?? [];
        } catch (RequestException $e) {
            Log::info('ImageLibraryService: Google media list unavailable', [
                'business_id' => $business->id,
                'status' => $e->getResponse()?->getStatusCode(),
            ]);

            return 0;
        } catch (\Throwable $e) {
            Log::info('ImageLibraryService: Google media list failed', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }

        $known = array_fill_keys(
            $business->images()->whereNotNull('google_media_name')->pluck('google_media_name')->all(),
            true
        );

        $startedAt = microtime(true);
        $imported = 0;

        foreach ($items as $item) {
            if ($imported >= self::MAX_NEW_GOOGLE_PER_RUN
                || (microtime(true) - $startedAt) > self::DOWNLOAD_BUDGET_SECONDS) {
                break;
            }

            $name = $item['name'] ?? null;
            $category = strtoupper((string) ($item['locationAssociation']['category'] ?? ''));
            $googleUrl = $item['googleUrl'] ?? null;

            if (($item['mediaFormat'] ?? null) !== 'PHOTO'
                || ! $name || ! $googleUrl || isset($known[$name])
                || in_array($category, self::GOOGLE_SKIP_CATEGORIES, true)) {
                continue;
            }

            try {
                $bytes = $this->download(self::fullSizeGoogleUrl($googleUrl)) ?? $this->download($googleUrl);

                if ($bytes === null) {
                    continue;
                }

                $image = $this->store($business, $this->processor->process($bytes), [
                    'source' => BusinessImage::SOURCE_GOOGLE,
                    'source_url' => $googleUrl,
                    'google_media_name' => $name,
                    'google_category' => $category !== '' ? $category : null,
                ]);

                if ($image) {
                    $imported++;
                }
            } catch (ImageRejected) {
                continue;
            } catch (\Throwable $e) {
                Log::info('ImageLibraryService: Could not import a Google photo', [
                    'business_id' => $business->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("ImageLibraryService: Imported {$imported} Google photos for business {$business->id}");

        return $imported;
    }

    /**
     * The largest copy of a Google hosted photo.
     *
     * googleUrl points at lh3.googleusercontent.com, which serves a reduced copy
     * unless the URL ends in a size option. Google does not document this for
     * the Business Profile API, so the caller falls back to the plain URL if the
     * sized one fails. We ask for 2048px, the most we would keep anyway.
     */
    public static function fullSizeGoogleUrl(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (! str_ends_with($host, 'googleusercontent.com')) {
            return $url;
        }

        // An existing option such as "=s300" or "=w400-h300-k-no" is replaced.
        $stripped = preg_replace('/=[a-z0-9\-]+$/i', '', $url);

        return $stripped.'=s'.ImageProcessor::MAX_LONG_SIDE;
    }

    /**
     * Add a photo the owner uploaded on the Photos page.
     *
     * @return array{image: BusinessImage, created: bool}
     *
     * @throws ImageRejected
     */
    public function storeUpload(Business $business, string $bytes): array
    {
        $processed = $this->processor->process($bytes);
        $hash = sha1($processed['bytes']);

        // The same photo uploaded twice, or already found on their website.
        $existing = $business->images()->where('content_hash', $hash)->first();
        if ($existing) {
            return ['image' => $existing, 'created' => false];
        }

        $image = $this->store($business, $processed, ['source' => BusinessImage::SOURCE_UPLOAD], ownerChosen: true);

        if (! $image) {
            // Lost a race with an identical upload; hand back the winner.
            $image = $business->images()->where('content_hash', $hash)->firstOrFail();

            return ['image' => $image, 'created' => false];
        }

        return ['image' => $image, 'created' => true];
    }

    public function uploadsRemaining(Business $business): int
    {
        return max(0, self::MAX_UPLOADS_TOTAL - $business->images()->where('source', BusinessImage::SOURCE_UPLOAD)->count());
    }

    /**
     * Record an AI image that has just been attached to a post.
     *
     * It is stored once, under the post's own path, and not copied into the
     * library folder: the row only exists so the owner can see it on the Photos
     * page. Failure here must never cost the post its image, so it is quiet.
     */
    public function recordAiImage(Business $business, string $storagePath, string $url, string $bytes): ?BusinessImage
    {
        try {
            $size = @getimagesizefromstring($bytes) ?: [null, null];

            return BusinessImage::create([
                'business_id' => $business->id,
                'source' => BusinessImage::SOURCE_AI,
                'storage_path' => $storagePath,
                'url' => $url,
                'width' => $size[0],
                'height' => $size[1],
                'content_hash' => sha1($bytes),
                'last_used_at' => now(),
                'use_count' => 1,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ImageLibraryService: Could not record an AI image in the library', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    // ── Using ──────────────────────────────────────────────────────────────

    /**
     * The library photo a new post should use, or null when there is none.
     *
     * The use is recorded straight away, so the next post written in the same
     * run (another platform, or the next day's slot) moves on to a different photo.
     */
    public function pickForPost(Business $business, ?string $pageUrl, string $platform, ?string $postText = null): ?BusinessImage
    {
        $images = $business->images()
            ->where('is_enabled', true)
            ->where('source', '!=', BusinessImage::SOURCE_AI)
            ->whereNotNull('vetted_at')
            ->whereIn('kind', BusinessImage::USABLE_KINDS)
            ->get();

        $chosen = $this->picker->choose(
            $images->map(fn (BusinessImage $image) => [
                'id' => $image->id,
                'source' => $image->source,
                'page_url' => $image->page_url,
                'is_enabled' => $image->is_enabled,
                'last_used_at' => $image->last_used_at,
                'vetted' => $image->vetted_at !== null,
                'kind' => $image->kind,
                'description' => $image->description,
            ])->all(),
            $pageUrl,
            now(),
            postText: $postText
        );

        if (! $chosen) {
            return null;
        }

        $image = $images->firstWhere('id', $chosen['id']);

        BusinessImage::whereKey($image->id)->update([
            'last_used_at' => now(),
            'use_count' => DB::raw('use_count + 1'),
        ]);

        Log::info("ImageLibraryService: Using library photo {$image->id} on {$platform}", [
            'business_id' => $business->id,
            'source' => $image->source,
            'same_page' => $pageUrl !== null && $image->page_url !== null
                && ImagePicker::normaliseUrl($pageUrl) === ImagePicker::normaliseUrl($image->page_url),
        ]);

        return $image->refresh();
    }

    /**
     * Delete a photo, or switch it off if an unpublished post still carries it.
     *
     * Deleting the file under a scheduled post would have it go out with a
     * broken image, or fail outright on platforms that fetch the URL at publish
     * time. A published post has already been copied by the platform, but its
     * card in postd would lose its picture, so the file is kept for those too
     * and only the library row goes.
     *
     * @return array{deleted: bool, disabled: bool, upcoming_posts: int, message: string}
     */
    public function remove(BusinessImage $image): array
    {
        $postsUsing = fn () => Post::where('business_id', $image->business_id)
            ->whereJsonContains('media_urls', $image->url);

        $upcoming = $postsUsing()
            ->whereIn('status', [
                Post::STATUS_PENDING,
                Post::STATUS_APPROVED,
                Post::STATUS_SCHEDULED,
                Post::STATUS_DISPATCHING,
                Post::STATUS_FAILED,
            ])
            ->count();

        if ($upcoming > 0) {
            $image->update(['is_enabled' => false]);

            return [
                'deleted' => false,
                'disabled' => true,
                'upcoming_posts' => $upcoming,
                'message' => $upcoming === 1
                    ? 'This photo is on a post that has not gone out yet, so we have switched it off instead of deleting it. It will not be used on any new posts. To remove it completely, take it off that post first.'
                    : "This photo is on {$upcoming} posts that have not gone out yet, so we have switched it off instead of deleting it. It will not be used on any new posts. To remove it completely, take it off those posts first.",
            ];
        }

        if (! $postsUsing()->where('status', Post::STATUS_POSTED)->exists()) {
            $disk = Storage::disk('s3');
            $disk->delete(array_filter([$image->storage_path, $image->thumbnail_path]));
        }

        $image->delete();

        return [
            'deleted' => true,
            'disabled' => false,
            'upcoming_posts' => 0,
            'message' => 'Photo deleted.',
        ];
    }

    // ── Internals ──────────────────────────────────────────────────────────

    /**
     * Save processed bytes and create the row. Null when this business already
     * has the same photo, which is how every duplicate across pages and sources
     * is caught.
     *
     * @param  array{bytes: string, width: int, height: int, thumbnail: string|null}  $processed
     * @param  array<string, mixed>  $attributes
     */
    private function store(Business $business, array $processed, array $attributes, bool $ownerChosen = false): ?BusinessImage
    {
        $hash = sha1($processed['bytes']);

        if ($business->images()->where('content_hash', $hash)->exists()) {
            return null;
        }

        $perceptualHash = $processed['perceptual_hash'] ?? null;
        $duplicateOf = $perceptualHash ? $this->nearDuplicateOf($business, $perceptualHash) : null;

        // A near-duplicate is still stored, switched off with the reason, so the
        // owner can see why and the same file is not downloaded again tomorrow.
        $vetting = $duplicateOf
            ? [
                'is_enabled' => false,
                'vetting_note' => ImageVetter::NOTE_DUPLICATE,
                'vetted_at' => now(),
                'kind' => $duplicateOf->kind,
                'description' => $duplicateOf->description,
            ]
            : $this->vetting($processed['thumbnail'] ?? $processed['bytes'], $ownerChosen);

        $disk = Storage::disk('s3');
        $path = "library/{$business->id}/{$hash}.jpg";

        if (! $disk->put($path, $processed['bytes'], 'public')) {
            throw new \RuntimeException("Could not store {$path}");
        }

        $thumbPath = null;
        if ($processed['thumbnail'] !== null) {
            $thumbPath = "library/{$business->id}/{$hash}_thumb.jpg";
            if (! $disk->put($thumbPath, $processed['thumbnail'], 'public')) {
                $thumbPath = null;
            }
        }

        try {
            return BusinessImage::create($attributes + $vetting + [
                'business_id' => $business->id,
                'perceptual_hash' => $perceptualHash,
                'storage_path' => $path,
                'url' => $disk->url($path),
                'thumbnail_path' => $thumbPath,
                'thumbnail_url' => $thumbPath ? $disk->url($thumbPath) : null,
                'width' => $processed['width'],
                'height' => $processed['height'],
                'content_hash' => $hash,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A parallel import stored the same photo first. The file path is
            // the same, so the winner's row already points at it.
            return null;
        }
    }

    /**
     * The vetting columns for a newly stored photo.
     *
     * If the check cannot run (API down, timeout), the photo is stored
     * unvetted, which keeps it off posts until vetPending() gets to it.
     * Uploads are vetted for their description only: the owner picked them,
     * so they are never switched off on the model's say-so.
     *
     * @return array<string, mixed>
     */
    private function vetting(string $jpegBytes, bool $ownerChosen): array
    {
        try {
            $verdict = $this->vetter->vet($jpegBytes);
        } catch (\Throwable $e) {
            Log::info('ImageLibraryService: Photo left unvetted for now', ['error' => $e->getMessage()]);

            return ['vetted_at' => null];
        }

        return [
            'kind' => $verdict['kind'],
            'description' => $verdict['description'] ?: null,
            'vetted_at' => now(),
            'vetting_note' => $ownerChosen ? null : $verdict['note'],
            'is_enabled' => $ownerChosen || $verdict['usable'],
        ];
    }

    /** An existing photo in this business's library that looks the same, if any. */
    private function nearDuplicateOf(Business $business, string $perceptualHash, ?string $exceptId = null): ?BusinessImage
    {
        return $business->images()
            ->whereNotNull('perceptual_hash')
            ->where('source', '!=', BusinessImage::SOURCE_AI)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->oldest()
            ->get(['id', 'perceptual_hash', 'kind', 'description', 'created_at'])
            ->first(fn (BusinessImage $image) => ImageProcessor::isNearDuplicate($image->perceptual_hash, $perceptualHash));
    }

    /**
     * Vet photos that have not been looked at yet.
     *
     * Covers photos imported before vetting existed, and any whose check failed
     * at import. A duplicate of an older photo is switched off rather than
     * vetted. Run daily by images:vet, and by hand after a backfill.
     *
     * @return array{vetted: int, switched_off: int, failed: int}
     */
    public function vetPending(?Business $business = null, int $limit = 50): array
    {
        $counts = ['vetted' => 0, 'switched_off' => 0, 'failed' => 0];
        $disk = Storage::disk('s3');

        $images = BusinessImage::query()
            ->whereNull('vetted_at')
            ->where('source', '!=', BusinessImage::SOURCE_AI)
            ->when($business, fn ($query) => $query->where('business_id', $business->id))
            ->oldest()
            ->limit($limit)
            ->get();

        foreach ($images as $image) {
            try {
                $bytes = $disk->get($image->thumbnail_path ?: $image->storage_path);

                if (! $bytes) {
                    throw new \RuntimeException('file missing');
                }

                $perceptualHash = $image->perceptual_hash
                    ?? ImageProcessor::perceptualHashOfBytes($disk->get($image->storage_path) ?: $bytes);

                $duplicateOf = $perceptualHash
                    ? $this->nearDuplicateOf($image->business, $perceptualHash, $image->id)
                    : null;

                // Only a duplicate of an older photo gives way, so of two copies
                // the first imported is the one kept.
                if ($duplicateOf && $duplicateOf->created_at->lessThanOrEqualTo($image->created_at)) {
                    $image->update([
                        'perceptual_hash' => $perceptualHash,
                        'vetted_at' => now(),
                        'vetting_note' => ImageVetter::NOTE_DUPLICATE,
                        'is_enabled' => $image->source === BusinessImage::SOURCE_UPLOAD ? $image->is_enabled : false,
                    ]);
                    $counts['switched_off']++;

                    continue;
                }

                $vetting = $this->vetting($bytes, $image->source === BusinessImage::SOURCE_UPLOAD);

                if ($vetting['vetted_at'] === null) {
                    $counts['failed']++;

                    continue;
                }

                // A photo the owner has already switched off stays off: vetting
                // can only ever narrow what the owner allowed, never widen it.
                if ($image->is_enabled === false) {
                    unset($vetting['is_enabled']);
                }

                $image->update($vetting + ['perceptual_hash' => $perceptualHash]);
                $counts['vetted']++;

                if (($vetting['is_enabled'] ?? true) === false) {
                    $counts['switched_off']++;
                }
            } catch (\Throwable $e) {
                $counts['failed']++;
                Log::warning('ImageLibraryService: Could not vet a photo', [
                    'image_id' => $image->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $counts;
    }

    /**
     * Fetch an image, or null if the response is not a usable image type or is too big.
     *
     * @throws \Throwable  On network failures, which may succeed another time.
     */
    private function download(string $url): ?string
    {
        if (! $this->isPublicUrl($url)) {
            return null;
        }

        $response = $this->http->get($url, [
            'stream' => true,
            'http_errors' => false,
            'allow_redirects' => [
                'max' => 5,
                'on_redirect' => function ($request, $response, $uri) {
                    if (! $this->isPublicUrl((string) $uri)) {
                        throw new \RuntimeException('Redirected to a private address.');
                    }
                },
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $type = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));
        $length = (int) $response->getHeaderLine('Content-Length');

        if (! in_array($type, self::IMAGE_TYPES, true) || $length > self::MAX_DOWNLOAD_BYTES) {
            $response->getBody()->close();

            return null;
        }

        // Read in chunks and stop at the limit, since Content-Length is often absent.
        $body = $response->getBody();
        $bytes = '';

        while (! $body->eof()) {
            $bytes .= $body->read(65536);

            if (strlen($bytes) > self::MAX_DOWNLOAD_BYTES) {
                $body->close();

                return null;
            }
        }

        return $bytes === '' ? null : $bytes;
    }

    /**
     * Refuse anything that resolves to a private or reserved address.
     *
     * The URLs come from pages on a website the customer controls, and this
     * runs on our server, so without this a page could point an image at the
     * droplet's metadata service or at Redis and have us fetch it.
     */
    private function isPublicUrl(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if (isset($this->hostIsPublic[$host])) {
            return $this->hostIsPublic[$host];
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        $public = $ips !== [] && array_reduce(
            $ips,
            fn (bool $ok, string $ip) => $ok && (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE),
            true
        );

        return $this->hostIsPublic[$host] = $public;
    }
}
