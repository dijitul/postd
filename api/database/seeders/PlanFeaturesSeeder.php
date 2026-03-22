<?php

namespace Database\Seeders;

use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

class PlanFeaturesSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'plan_name' => 'starter',
                'display_name' => 'Starter',
                'price_pence' => 1900,
                'platform_limit' => 2,
                'tiktok_included' => false,
                'gbp_included' => true,
                'ai_images_included' => true,
                'posts_per_month' => null,
                'approval_workflow' => true,
                'analytics_included' => true,
                'priority_support' => false,
                'stripe_price_id' => null,
                'features_list' => [
                    '2 social platforms of your choice',
                    'Google Business Profile (always free)',
                    'AI-generated posts daily',
                    'Post approval inbox',
                    'AI image generation',
                    'Analytics dashboard',
                ],
            ],
            [
                'plan_name' => 'growth',
                'display_name' => 'Growth',
                'price_pence' => 3900,
                'platform_limit' => 4,
                'tiktok_included' => false,
                'gbp_included' => true,
                'ai_images_included' => true,
                'posts_per_month' => null,
                'approval_workflow' => true,
                'analytics_included' => true,
                'priority_support' => false,
                'stripe_price_id' => null,
                'features_list' => [
                    '4 social platforms of your choice',
                    'Google Business Profile (always free)',
                    'AI-generated posts daily',
                    'Post approval inbox',
                    'AI image generation',
                    'Full analytics dashboard',
                    'Local news hooks',
                    'Review-powered content',
                    'TikTok add-on available',
                ],
            ],
            [
                'plan_name' => 'pro',
                'display_name' => 'Pro',
                'price_pence' => 6900,
                'platform_limit' => null,
                'tiktok_included' => true,
                'gbp_included' => true,
                'ai_images_included' => true,
                'posts_per_month' => null,
                'approval_workflow' => true,
                'analytics_included' => true,
                'priority_support' => true,
                'stripe_price_id' => null,
                'features_list' => [
                    'All social platforms (unlimited)',
                    'Google Business Profile (always free)',
                    'TikTok video generation included',
                    'AI-generated posts daily',
                    'Post approval inbox',
                    'AI image generation',
                    'Full analytics dashboard',
                    'Local news hooks',
                    'Review-powered content',
                    'Priority support',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            PlanFeature::updateOrCreate(
                ['plan_name' => $plan['plan_name']],
                $plan
            );
        }
    }
}
