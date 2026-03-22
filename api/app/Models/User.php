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

    public function scopeOnTrial($query)
    {
        return $query->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now());
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
                ->orWhere('trial_ends_at', '>', now());
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

    public function hasActivePlan(): bool
    {
        return $this->subscribed() || $this->isOnValidTrial();
    }

    public function activePlanName(): string
    {
        if ($this->subscribed('default')) {
            $subscription = $this->subscription('default');
            return $subscription?->stripe_price ?? 'unknown';
        }
        if ($this->isOnValidTrial()) {
            return 'growth'; // trial defaults to growth tier
        }
        return 'none';
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
