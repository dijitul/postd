<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialConnection extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'platform',
        'access_token',
        'refresh_token',
        'expires_at',
        'is_active',
        'token_refresh_attempted',
        'last_used_at',
        'last_error_at',
        'last_error_message',
        'scopes',
        'raw_token_data',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
        'raw_token_data',
    ];

    protected function casts(): array
    {
        return [
            // Encrypt tokens transparently in the database
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'token_refresh_attempted' => 'boolean',
            'last_used_at' => 'datetime',
            'last_error_at' => 'datetime',
            'scopes' => 'array',
            'raw_token_data' => 'encrypted:array',
        ];
    }

    // Relationships

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function platformAccounts(): HasMany
    {
        return $this->hasMany(PlatformAccount::class, 'connection_id');
    }

    public function selectedAccount(): HasMany
    {
        return $this->hasMany(PlatformAccount::class, 'connection_id')
            ->where('is_selected', true);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'connection_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeExpiringWithinDays($query, int $days)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now());
    }

    // Helpers

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false; // no expiry — long-lived token
        }
        return $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $withinDays = 7): bool
    {
        if ($this->expires_at === null) {
            return false;
        }
        return $this->expires_at->isBefore(now()->addDays($withinDays));
    }

    public function markError(string $message): void
    {
        $this->update([
            'last_error_at' => now(),
            'last_error_message' => $message,
        ]);
    }

    public function markSuccessfulUse(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
