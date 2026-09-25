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
        // GBP integration is available on all plans
        if ($platform === 'google_business_profile') {
            return true;
        }

        // All other platforms (facebook, twitter, linkedin) are included on every plan
        return true;
    }
}
