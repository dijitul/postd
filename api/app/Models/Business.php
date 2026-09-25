<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'industry',
        'website_url',
        'google_reviews_url',
        'google_place_id',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'postcode',
        'country',
        'tone',
        'description',
        'usp_notes',
        'onboarding_complete',
        'onboarding_completed_at',
        'last_scraped_at',
        'last_generated_at',
        'is_active',
        'images_google_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'onboarding_complete' => 'boolean',
            'is_active' => 'boolean',
            'onboarding_completed_at' => 'datetime',
            'last_scraped_at' => 'datetime',
            'last_generated_at' => 'datetime',
            'images_google_synced_at' => 'datetime',
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(BusinessSetting::class);
    }

    public function socialConnections(): HasMany
    {
        return $this->hasMany(SocialConnection::class);
    }

    public function activeSocialConnections(): HasMany
    {
        return $this->hasMany(SocialConnection::class)->where('is_active', true);
    }

    public function contentSources(): HasMany
    {
        return $this->hasMany(ContentSource::class);
    }

    public function contentBriefs(): HasMany
    {
        return $this->hasMany(ContentBrief::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /** The photo library posts draw their images from. See ImageLibraryService. */
    public function images(): HasMany
    {
        return $this->hasMany(BusinessImage::class);
    }

    public function pendingPosts(): HasMany
    {
        return $this->hasMany(Post::class)->where('status', 'pending');
    }

    public function scheduledPosts(): HasMany
    {
        return $this->hasMany(Post::class)->where('status', 'scheduled');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOnboarded($query)
    {
        return $query->where('onboarding_complete', true);
    }

    // Helpers

    public function hasConnectedPlatform(string $platform): bool
    {
        return $this->socialConnections()
            ->where('platform', $platform)
            ->where('is_active', true)
            ->exists();
    }

    public function getConnectionForPlatform(string $platform): ?SocialConnection
    {
        return $this->socialConnections()
            ->where('platform', $platform)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Active connections on platforms postd still supports. A connection left
     * over from a retired platform is ignored, so nothing is generated for it.
     *
     * Oldest first. When a plan allows fewer platforms than are connected (the
     * trial ended and Local was chosen), the earliest connections are the ones
     * kept active; see Entitlements::usablePlatforms().
     */
    public function connectedPlatforms(): array
    {
        return $this->activeSocialConnections()
            ->whereIn('platform', Post::PLATFORMS)
            ->orderBy('created_at')
            ->pluck('platform')
            ->unique()
            ->values()
            ->toArray();
    }

    public function getOrCreateSettings(): BusinessSetting
    {
        return $this->settings ?? BusinessSetting::create(['business_id' => $this->id]);
    }
}
