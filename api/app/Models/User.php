<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use Billable;
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'trial_ends_at',
        'comped_plan',
        'comped_at',
        'comped_until',
        'comped_by',
        'comp_note',
        'referral_code',
        'referred_by',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'stripe_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'trial_ends_at' => 'datetime',
            'comped_at' => 'datetime',
            'comped_until' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    // Relationships

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    public function business(): HasOne
    {
        return $this->hasOne(Business::class)->latest();
    }

    // Scopes

    public function scopeAdmins($query)
    {
        return $query->where('is_admin', true);
    }

    /**
     * Users currently inside their trial window.
     *
     * Deliberately not named scopeOnTrial. Cashier's Billable trait already
     * defines an onTrial() instance method, and Model::__callStatic resolves
     * to that rather than the scope, handing back a bool instead of a builder.
     */
    public function scopeTrialing($query)
    {
        return $query->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now());
    }

    public function scopeComped($query)
    {
        return $query->whereNotNull('comped_plan')
            ->where(fn ($q) => $q->whereNull('comped_until')->orWhere('comped_until', '>', now()));
    }

    public function scopeTrialExpired($query)
    {
        return $query->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now());
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereHas('subscriptions', fn ($s) => $s->active())
                ->orWhere('trial_ends_at', '>', now())
                ->orWhere(fn ($c) => $c->whereNotNull('comped_plan')
                    ->where(fn ($u) => $u->whereNull('comped_until')->orWhere('comped_until', '>', now())));
        });
    }

    // Helpers

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function isOnValidTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Comped: given full access by the Dijitul team without paying.
     */
    public function isComped(): bool
    {
        if ($this->comped_plan === null) {
            return false;
        }

        return $this->comped_until === null || $this->comped_until->isFuture();
    }

    public function hasActivePlan(): bool
    {
        return $this->subscribed() || $this->isOnValidTrial() || $this->isComped();
    }

    /**
     * The plan key (starter/growth/pro) currently in force, not the Stripe
     * price ID: callers compare this against config('cashier.plans') keys.
     */
    public function activePlanName(): string
    {
        if ($this->isComped()) {
            return $this->comped_plan;
        }
        if ($this->subscribed('default')) {
            $priceId = $this->subscription('default')?->stripe_price;

            return self::planKeyForPriceId($priceId) ?? 'unknown';
        }
        if ($this->isOnValidTrial()) {
            return config('cashier.trial_plan', 'growth');
        }

        return 'none';
    }

    /**
     * Map a Stripe price ID back to its configured plan key.
     */
    public static function planKeyForPriceId(?string $priceId): ?string
    {
        if (! $priceId) {
            return null;
        }

        foreach (config('cashier.plans', []) as $key => $plan) {
            if (($plan['stripe_price_id'] ?? null) === $priceId) {
                return $key;
            }
        }

        return null;
    }

    /**
     * How this account is paying for postd, for admin reporting.
     * One of: comped, subscribed, trialing, expired, none.
     */
    public function billingStatus(): string
    {
        if ($this->isComped()) {
            return 'comped';
        }
        if ($this->subscribed('default')) {
            return 'subscribed';
        }
        if ($this->isOnValidTrial()) {
            return 'trialing';
        }
        if ($this->trial_ends_at !== null) {
            return 'expired';
        }

        return 'none';
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
