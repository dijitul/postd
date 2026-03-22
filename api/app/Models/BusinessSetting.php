<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessSetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'business_id',
        'auto_approve_posts',
        'approval_window_hours',
        'post_time_windows',
        'platform_settings',
        'content_themes',
        'content_exclusions',
        'include_local_news_hooks',
        'include_review_content',
        'generate_images',
        'generate_tiktok_videos',
        'posts_per_week_facebook',
        'posts_per_week_instagram',
        'posts_per_week_twitter',
        'posts_per_week_linkedin',
        'posts_per_week_tiktok',
        'posts_per_week_gbp',
        'notify_post_approved',
        'notify_post_failed',
        'notify_weekly_summary',
        'notify_token_expiring',
    ];

    protected function casts(): array
    {
        return [
            'auto_approve_posts' => 'boolean',
            'post_time_windows' => 'array',
            'platform_settings' => 'array',
            'content_themes' => 'array',
            'content_exclusions' => 'array',
            'include_local_news_hooks' => 'boolean',
            'include_review_content' => 'boolean',
            'generate_images' => 'boolean',
            'generate_tiktok_videos' => 'boolean',
            'notify_post_approved' => 'boolean',
            'notify_post_failed' => 'boolean',
            'notify_weekly_summary' => 'boolean',
            'notify_token_expiring' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the posting window for a given day.
     *
     * @return array{start: string, end: string}|null
     */
    public function getTimeWindowForDay(string $day): ?array
    {
        if (empty($this->post_time_windows)) {
            return null; // no restriction — post any time
        }

        $day = strtolower($day);
        return $this->post_time_windows[$day] ?? null;
    }

    /**
     * Get posts-per-week target for a given platform.
     */
    public function getPostsPerWeekForPlatform(string $platform): int
    {
        $key = "posts_per_week_{$platform}";
        return $this->$key ?? 3;
    }
}
