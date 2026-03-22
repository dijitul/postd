<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanFeature extends Model
{
    protected $fillable = [
        'plan_name',
        'display_name',
        'price_pence',
        'platform_limit',
        'tiktok_included',
        'gbp_included',
        'ai_images_included',
        'posts_per_month',
        'approval_workflow',
        'analytics_included',
        'priority_support',
        'stripe_price_id',
        'features_list',
    ];

    protected function casts(): array
    {
        return [
            'tiktok_included' => 'boolean',
            'gbp_included' => 'boolean',
            'ai_images_included' => 'boolean',
            'approval_workflow' => 'boolean',
            'analytics_included' => 'boolean',
            'priority_support' => 'boolean',
            'features_list' => 'array',
        ];
    }

    public static function forPlan(string $planName): ?self
    {
        return static::where('plan_name', $planName)->first();
    }

    public function getPriceGbp(): string
    {
        return '£'.number_format($this->price_pence / 100, 2);
    }

    public function allowsPlatform(string $platform, int $connectedCount): bool
    {
        // GBP is always free on all plans
        if ($platform === 'google_business_profile') {
            return true;
        }

        // TikTok requires the tiktok_included flag
        if ($platform === 'tiktok') {
            return $this->tiktok_included;
        }

        // All other platforms checked against the limit
        if ($this->platform_limit === null) {
            return true; // unlimited
        }

        return $connectedCount < $this->platform_limit;
    }
}
