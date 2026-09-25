<?php

namespace App\Modules\Media\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessImage;
use App\Modules\Media\Exceptions\ImageRejected;
use App\Modules\Media\Services\ImageLibraryService;
use App\Modules\Scraping\Jobs\ScrapeBusinessJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The Photos page: the business's image library.
 *
 * Everything is scoped to $user->business, the location the user is currently
 * working on, so an Agency user switching locations sees that location's photos.
 */
class ImageController extends Controller
{
    private const PER_PAGE = 48;

    // One manual check an hour. Each one re-crawls the customer's website.
    private const REFRESH_COOLDOWN_MINUTES = 60;

    public function __construct(private readonly ImageLibraryService $library) {}

    public function index(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        if (! $business) {
            return response()->json(['images' => [], 'meta' => []]);
        }

        $images = $business->images()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', self::PER_PAGE))));

        $nextGoogle = $this->library->nextGoogleSyncAt($business);

        return response()->json([
            'images' => $images->getCollection()->map(fn (BusinessImage $image) => $this->format($image))->values(),
            'meta' => [
                'current_page' => $images->currentPage(),
                'last_page' => $images->lastPage(),
                'per_page' => $images->perPage(),
                'total' => $images->total(),
            ],
            'library' => [
                'last_checked_at' => $business->last_scraped_at?->toIso8601String(),
                'google_checked_at' => $business->images_google_synced_at?->toIso8601String(),
                'google_next_check_at' => $nextGoogle?->toIso8601String(),
                'refresh_available_at' => $this->refreshAvailableAt($business)?->toIso8601String(),
                'has_website' => (bool) $business->website_url,
                'has_google' => $business->hasConnectedPlatform('google_business_profile'),
            ],
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $image = $this->findForUser($request, $id);

        $request->validate(['is_enabled' => ['required', 'boolean']]);

        $image->update(['is_enabled' => $request->boolean('is_enabled')]);

        return response()->json([
            'message' => $image->is_enabled ? 'Photo switched on.' : 'Photo switched off. It will not be used on new posts.',
            'image' => $this->format($image->fresh()),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $image = $this->findForUser($request, $id);
        $result = $this->library->remove($image);

        return response()->json($result + [
            'image' => $result['deleted'] ? null : $this->format($image->fresh()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        if (! $business) {
            return response()->json(['message' => 'Business not found.'], 404);
        }

        // Size is checked here; the type is checked by reading the file itself
        // in ImageProcessor, since browsers report HEIC and WebP inconsistently.
        $request->validate([
            'image' => ['required', 'file', 'max:15360'],
        ], [
            'image.max' => 'That photo is over 15MB. Please upload a smaller copy.',
            'image.required' => 'Please choose a photo to upload.',
        ]);

        if ($this->library->uploadsRemaining($business) < 1) {
            return response()->json([
                'message' => 'Your photo library is full. Delete a few uploads you no longer need, then try again.',
                'error' => 'library_full',
            ], 422);
        }

        $file = $request->file('image');

        if (preg_match('/\.(heic|heif)$/i', (string) $file->getClientOriginalName())) {
            return $this->rejected(new ImageRejected('heic'));
        }

        try {
            $result = $this->library->storeUpload($business, (string) file_get_contents($file->getRealPath()));
        } catch (ImageRejected $e) {
            return $this->rejected($e);
        }

        return response()->json([
            'message' => $result['created'] ? 'Photo added.' : 'You already have this photo in your library.',
            'created' => $result['created'],
            'image' => $this->format($result['image']),
        ], $result['created'] ? 201 : 200);
    }

    /**
     * Check the website and Google profile for new photos now.
     *
     * Runs the normal scrape, which queues the photo import when it finishes.
     * Google is still read at most once a week (its API quota is tiny), so the
     * response says when it will next be checked.
     */
    public function refresh(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        if (! $business) {
            return response()->json(['message' => 'Business not found.'], 404);
        }

        $availableAt = $this->refreshAvailableAt($business);
        if ($availableAt) {
            return response()->json([
                'message' => 'We checked for new photos recently. You can check again after '.$availableAt->timezone('Europe/London')->format('g:ia').'.',
                'error' => 'too_soon',
                'refresh_available_at' => $availableAt->toIso8601String(),
            ], 429);
        }

        Cache::put($this->refreshKey($business), now()->toIso8601String(), now()->addMinutes(self::REFRESH_COOLDOWN_MINUTES));

        ScrapeBusinessJob::dispatch($business)->onQueue('scraping');

        $nextGoogle = $this->library->nextGoogleSyncAt($business);
        $message = 'Checking for new photos now. Anything new will appear here within a few minutes.';

        if ($nextGoogle && $business->hasConnectedPlatform('google_business_profile')) {
            $message .= ' Google photos were checked recently, so they will next be checked on '
                .$nextGoogle->timezone('Europe/London')->format('j F').'.';
        }

        return response()->json([
            'message' => $message,
            'refresh_available_at' => now()->addMinutes(self::REFRESH_COOLDOWN_MINUTES)->toIso8601String(),
        ], 202);
    }

    private function rejected(ImageRejected $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'error' => $e->reason,
        ], 422);
    }

    private function refreshKey(Business $business): string
    {
        return "images_refresh_{$business->id}";
    }

    private function refreshAvailableAt(Business $business): ?\Carbon\CarbonInterface
    {
        $last = Cache::get($this->refreshKey($business));

        $availableAt = $last ? \Illuminate\Support\Carbon::parse($last)->addMinutes(self::REFRESH_COOLDOWN_MINUTES) : null;

        return $availableAt?->isFuture() ? $availableAt : null;
    }

    /** The image, if it belongs to the business the user is working on. */
    private function findForUser(Request $request, string $id): BusinessImage
    {
        $business = $request->user()->business;

        // Postgres rejects a malformed uuid with an error rather than no rows.
        $image = $business && Str::isUuid($id) ? BusinessImage::where('business_id', $business->id)->whereKey($id)->first() : null;

        if (! $image) {
            abort(404, 'Photo not found.');
        }

        return $image;
    }

    private function format(BusinessImage $image): array
    {
        return [
            'id' => $image->id,
            'source' => $image->source,
            'url' => $image->url,
            'thumbnail_url' => $image->thumbnail_url ?? $image->url,
            'page_url' => $image->page_url,
            'google_category' => $image->google_category,
            'width' => $image->width,
            'height' => $image->height,
            'is_enabled' => $image->is_enabled,
            'last_used_at' => $image->last_used_at?->toIso8601String(),
            'use_count' => $image->use_count,
            'created_at' => $image->created_at?->toIso8601String(),
        ];
    }
}
