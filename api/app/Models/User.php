<?php

namespace App\Models;

use App\Modules\Billing\Services\PlanCatalogue;
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
        'current_business_id',
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

    /**
     * The business (location) the user is currently working on.
     *
     * Most accounts own exactly one. An Agency account can own several and
     * picks between them with the switcher, which records the choice in
     * current_business_id. It is an ordering rather than a filter, so a stale
     * id (a business since removed) falls back to the newest business instead
     * of leaving the user with none. Every controller reading $user->business
     * follows the switch without needing to know about it.
     */
    public function business(): HasOne
    {
        $relation = $this->hasOne(Business::class);

        // Read raw: a user fresh from create() has no such attribute loaded,
        // and strict mode would throw on the property instead of returning null.
        $currentId = $this->getAttributes()['current_business_id'] ?? null;

        if ($currentId) {
            $relation->orderByRaw('CASE WHEN businesses.id = ? THEN 0 ELSE 1 END', [$currentId]);
        }

        return $relation->latest();
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
     * The plan key (local/growth/agency, or legacy pro) currently in force,
     * not the Stripe price ID: callers compare this against the keys in
     * config('plans.plans'). 'none' means no plan, and 'unknown' means a
     * subscription to a Stripe price config/plans.php does not recognise.
     */
    public function activePlanName(): string
    {
        $catalogue = app(PlanCatalogue::class);

        if ($this->isComped()) {
            // Comps saved before the rename may still say 'starter'.
            return $catalogue->normalise($this->comped_plan) ?? $this->comped_plan;
        }
        if ($this->subscribed('default')) {
            return $this->subscribedPlanKey() ?? 'unknown';
        }
        if ($this->isOnValidTrial()) {
            return $catalogue->trialPlan();
        }

        return 'none';
    }

    /**
     * The plan behind the Stripe subscription, if there is one.
     *
     * Cashier only fills subscriptions.stripe_price for a single-price
     * subscription. An Agency subscription with extra locations carries two
     * prices, leaves that column null, and has to be read from its items.
     */
    public function subscribedPlanKey(): ?string
    {
        return $this->subscribedPlan()['plan'] ?? null;
    }

    /** 'monthly' or 'annual' for the current subscription, if any. */
    public function subscribedInterval(): ?string
    {
        return $this->subscribedPlan()['interval'] ?? null;
    }

    /** @return array{plan: string, interval: string}|null */
    private function subscribedPlan(): ?array
    {
        $subscription = $this->subscription('default');
        if (! $subscription) {
            return null;
        }

        $catalogue = app(PlanCatalogue::class);
        $prices = array_filter(array_merge(
            [$subscription->stripe_price],
            $subscription->items->pluck('stripe_price')->all()
        ));

        foreach ($prices as $priceId) {
            if ($match = $catalogue->lookupPrice($priceId)) {
                return $match;
            }
        }

        return null;
    }

    /**
     * Map a Stripe price ID back to its configured plan key.
     */
    public static function planKeyForPriceId(?string $priceId): ?string
    {
        return app(PlanCatalogue::class)->lookupPrice($priceId)['plan'] ?? null;
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

    /**
     * Tax rates Cashier attaches to every new subscription and Checkout session.
     *
     * UK VAT at 20%, added on top of the plan price. Existing subscriptions
     * keep whatever they were created with; adding VAT to those would raise
     * a customer's bill, so it is done deliberately, never on deploy.
     *
     * @return string[]
     */
    public function taxRates(): array
    {
        return array_values(array_filter([config('plans.vat_tax_rate_id')]));
    }
}
