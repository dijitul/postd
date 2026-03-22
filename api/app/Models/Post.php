<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    // Status constants — the full lifecycle of a post
    public const STATUS_PENDING = 'pending';       // generated, awaiting approval
    public const STATUS_APPROVED = 'approved';     // approved, awaiting scheduling
    public const STATUS_REJECTED = 'rejected';     // rejected, will not post
    public const STATUS_SCHEDULED = 'scheduled';   // scheduled for a specific time
    public const STATUS_POSTED = 'posted';         // successfully posted
    public const STATUS_FAILED = 'failed';         // all retries exhausted

    public const PLATFORMS = [
        'facebook',
        'instagram',
        'twitter',
        'linkedin',
        'tiktok',
        'google_business_profile',
    ];

    protected $fillable = [
        'business_id',
        'brief_id',
        'connection_id',
        'platform',
        'content',
        'content_edited',
        'media_urls',
        'hashtags',
        'status',
        'scheduled_at',
        'posted_at',
        'platform_post_id',
        'platform_post_url',
        'requires_approval',
        'approved_at',
        'approved_by',
        'retry_count',
        'last_retry_at',
        'failure_reason',
        'ai_metadata',
    ];

    protected function casts(): array
    {
        return [
            'media_urls' => 'array',
            'hashtags' => 'array',
            'ai_metadata' => 'array',
            'scheduled_at' => 'datetime',
            'posted_at' => 'datetime',
            'approved_at' => 'datetime',
            'last_retry_at' => 'datetime',
            'requires_approval' => 'boolean',
        ];
    }

    // Relationships

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function brief(): BelongsTo
    {
        return $this->belongsTo(ContentBrief::class, 'brief_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SocialConnection::class, 'connection_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PostAttempt::class);
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopePosted($query)
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeDueToPost($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now());
    }

    public function scopeInbox($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    // Helpers

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function canRetry(): bool
    {
        return $this->status === self::STATUS_FAILED && $this->retry_count < 3;
    }

    public function getEffectiveContent(): string
    {
        return $this->content_edited ?? $this->content;
    }

    public function approve(?User $approver = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $approver?->id,
        ]);
    }

    public function reject(): void
    {
        $this->update(['status' => self::STATUS_REJECTED]);
    }

    public function schedule(\DateTimeInterface $scheduledAt): void
    {
        $this->update([
            'status' => self::STATUS_SCHEDULED,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    public function markPosted(string $platformPostId, ?string $postUrl = null): void
    {
        $this->update([
            'status' => self::STATUS_POSTED,
            'posted_at' => now(),
            'platform_post_id' => $platformPostId,
            'platform_post_url' => $postUrl,
        ]);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
            'last_retry_at' => now(),
            'retry_count' => $this->retry_count + 1,
        ]);
    }
}
