<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user and start their 14-day trial.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'trial_ends_at' => now()->addDays(14),
                'referral_code' => Str::upper(Str::random(8)),
                'referred_by' => $this->resolveReferral($request->referral_code),
            ]);

            event(new Registered($user));

            return $user;
        });

        $user->notify(new \App\Modules\Notifications\WelcomeNotification($user));

        $token = $user->createToken('api-token', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully. Welcome to postd.uk!',
            'user' => $this->formatUser($user),
            'token' => $token,
            'trial_ends_at' => $user->trial_ends_at->toIso8601String(),
        ], 201);
    }

    /**
     * Login an existing user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->trashed()) {
            return response()->json([
                'message' => 'This account has been deleted.',
                'error' => 'account_deleted',
            ], 403);
        }

        // Revoke old tokens if not using device-specific tokens
        if (! $request->boolean('remember_device')) {
            $user->tokens()->where('name', 'api-token')->delete();
        }

        $expiry = $request->boolean('remember_device') ? now()->addDays(90) : now()->addDays(30);
        $token = $user->createToken('api-token', ['*'], $expiry)->plainTextToken;

        $user->update(['last_seen_at' => now()]);

        return response()->json([
            'message' => 'Logged in successfully.',
            'user' => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    /**
     * Return the authenticated user's data.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load('businesses');

        return response()->json([
            'user' => $this->formatUser($user),
            'businesses' => $user->businesses->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'industry' => $b->industry,
                'onboarding_complete' => $b->onboarding_complete,
                'connected_platforms' => $b->connectedPlatforms(),
            ]),
        ]);
    }

    /**
     * Logout — revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Send a password reset link.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            // Return a generic success message regardless to prevent email enumeration
        }

        return response()->json([
            'message' => 'If an account with that email exists, a password reset link has been sent.',
        ]);
    }

    /**
     * Reset the user's password.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete(); // invalidate all sessions
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Password reset successfully. Please log in.']);
    }

    /**
     * Verify the user's email address.
     */
    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link.'], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->markEmailAsVerified();

        return response()->json(['message' => 'Email verified successfully.']);
    }

    /**
     * Resend the email verification notification.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        if (! Hash::check($request->current_password, $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        // Invalidate all other sessions
        $request->user()->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password updated successfully.']);
    }

    /**
     * Update the user's profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
        ]);

        $emailChanged = $request->email !== $request->user()->email;

        $request->user()->update([
            'name' => $request->name,
            'email' => $request->email,
            'email_verified_at' => $emailChanged ? null : $request->user()->email_verified_at,
        ]);

        if ($emailChanged) {
            $request->user()->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $this->formatUser($request->user()->fresh()),
        ]);
    }

    /**
     * Soft-delete the user's account and all associated data (GDPR).
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required'],
            'confirmation' => ['required', 'in:DELETE'],
        ]);

        if (! Hash::check($request->password, $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => ['Password is incorrect.'],
            ]);
        }

        $user = $request->user();

        // Revoke all tokens
        $user->tokens()->delete();

        // Cancel any active Stripe subscription
        if ($user->subscribed()) {
            $user->subscription()->cancelNow();
        }

        // Soft-delete the user — cascade handled by models
        $user->delete();

        return response()->json(['message' => 'Your account has been deleted. We are sorry to see you go.']);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'is_admin' => $user->is_admin,
            'trial_ends_at' => $user->trial_ends_at?->toIso8601String(),
            'is_on_trial' => $user->isOnValidTrial(),
            'has_active_plan' => $user->hasActivePlan(),
            'active_plan' => $user->activePlanName(),
            'created_at' => $user->created_at->toIso8601String(),
        ];
    }

    private function resolveReferral(?string $referralCode): ?string
    {
        if (! $referralCode) {
            return null;
        }

        return User::where('referral_code', $referralCode)->value('id');
    }
}
