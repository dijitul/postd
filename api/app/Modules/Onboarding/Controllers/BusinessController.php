<?php

namespace App\Modules\Onboarding\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Services\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The location switcher for accounts with more than one business.
 *
 * Adding a location is not here: it runs the normal onboarding wizard, with
 * OnboardingController::createBusiness applying the location limit.
 */
class BusinessController extends Controller
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    /**
     * The account's locations, which one is current, and whether another can
     * be added.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $user->business;
        $entitlements = $this->entitlements->forUser($user);
        $extras = $this->entitlements->extraLocations($user);

        $businesses = $user->businesses()
            ->orderBy('created_at')
            ->get(['id', 'name', 'city', 'onboarding_complete', 'created_at']);

        $blocked = $entitlements->addLocationBlockReason($businesses->count(), $extras);

        return response()->json([
            'businesses' => $businesses->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'city' => $b->city,
                'onboarding_complete' => $b->onboarding_complete,
                'is_current' => $b->id === $current?->id,
            ]),
            'current_id' => $current?->id,
            'location_limit' => $entitlements->locationLimit($extras),
            // null: can add now. extra_location_required: can add for the
            // add-on price. location_limit: needs a different plan first.
            'add_location' => $blocked
                ? $this->entitlements->limitResponse($blocked, $entitlements)
                : null,
        ]);
    }

    /**
     * Make another of the user's businesses the current one.
     */
    public function switch(Request $request, string $id): JsonResponse
    {
        // Postgres rejects a malformed uuid with a query error, not a miss.
        abort_unless(Str::isUuid($id), 404);

        $user = $request->user();
        $business = $user->businesses()->findOrFail($id);

        $user->forceFill(['current_business_id' => $business->id])->save();

        return response()->json([
            'message' => "Now working on {$business->name}.",
            'business' => $business->only(['id', 'name', 'onboarding_complete']),
        ]);
    }
}
