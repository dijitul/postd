<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\ContentSource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create a demo user
        $user = User::firstOrCreate(
            ['email' => 'demo@postd.uk'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'trial_ends_at' => now()->addDays(14),
            ]
        );

        // Create a demo business
        $business = Business::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'The Artisan Bakery',
                'industry' => 'restaurant',
                'website_url' => 'https://example-bakery.co.uk',
                'google_reviews_url' => 'https://g.page/r/example/review',
                'tone' => 'friendly',
                'city' => 'Mansfield',
                'postcode' => 'NG18 1AB',
                'description' => 'A family-run artisan bakery in the heart of Mansfield, specialising in sourdough, pastries, and celebration cakes.',
                'usp_notes' => 'Hand-made daily, locally sourced ingredients, 15 years in Mansfield',
                'onboarding_complete' => true,
                'onboarding_completed_at' => now(),
                'last_scraped_at' => now()->subHour(),
                'last_generated_at' => now()->subHour(),
            ]
        );

        // Create default settings
        if (! $business->settings) {
            BusinessSetting::create([
                'business_id' => $business->id,
                'auto_approve_posts' => false,
                'approval_window_hours' => 24,
                'generate_images' => true,
                'include_local_news_hooks' => true,
                'include_review_content' => true,
            ]);
        }

        // Create some demo content sources
        ContentSource::firstOrCreate(
            ['business_id' => $business->id, 'type' => ContentSource::TYPE_WEBSITE],
            [
                'source_url' => 'https://example-bakery.co.uk',
                'raw_data' => 'Family-run artisan bakery in Mansfield. Fresh sourdough baked daily. Celebration cakes made to order. Open 7 days a week.',
                'structured_data' => [
                    'page_title' => 'The Artisan Bakery Mansfield',
                    'meta_description' => 'Handmade sourdough, pastries and celebration cakes in Mansfield',
                    'services' => ['Sourdough bread', 'Croissants', 'Celebration cakes', 'Pastries', 'Coffee'],
                    'opening_hours' => ['Mon-Fri: 7:30am - 5:00pm', 'Sat-Sun: 8:00am - 3:00pm'],
                ],
                'scraped_at' => now()->subHour(),
            ]
        );

        ContentSource::firstOrCreate(
            ['business_id' => $business->id, 'type' => ContentSource::TYPE_REVIEW],
            [
                'source_url' => 'https://g.page/r/example/review',
                'raw_data' => 'Absolutely love this bakery! The sourdough is the best I\'ve had outside of London. The staff are so friendly and it always smells amazing. Highly recommend the almond croissants!',
                'structured_data' => [
                    'author' => 'Sarah M.',
                    'rating' => 5,
                ],
                'sentiment_score' => 1.0,
                'scraped_at' => now()->subHour(),
            ]
        );

        // Create a few demo posts
        $platforms = ['facebook', 'instagram', 'google_business_profile'];
        foreach ($platforms as $i => $platform) {
            Post::firstOrCreate(
                ['business_id' => $business->id, 'platform' => $platform, 'status' => Post::STATUS_PENDING],
                [
                    'content' => $this->getDemoContent($platform, $business->name),
                    'hashtags' => ['artisanbakery', 'mansfield', 'sourdough'],
                    'requires_approval' => true,
                    'scheduled_at' => now()->addHours($i + 2),
                    'ai_metadata' => ['model' => 'gpt-4o', 'demo' => true],
                ]
            );
        }

        $this->command->info('Demo data seeded for '.$user->email.' / password: password');
    }

    private function getDemoContent(string $platform, string $businessName): string
    {
        return match ($platform) {
            'facebook' => "There's something special about the smell of fresh sourdough on a Monday morning. At {$businessName}, we've been baking ours the slow way for over 15 years — 48-hour fermentation, locally sourced flour, and a lot of love. Pop in this week and treat yourself. We're open from 7:30am Monday to Friday! What's your favourite thing to have with a fresh slice? 🍞",
            'instagram' => "Fresh from the oven and straight to your table. ✨ Our hand-shaped sourdough is baked daily using locally sourced Nottinghamshire flour — no shortcuts, ever. Come find us in Mansfield this week. \n\n#ArtisanBread #Sourdough #Mansfield #LocalBakery #FreshBread #BreadLovers #NottsFood",
            'google_business_profile' => "This week at The Artisan Bakery, we're featuring our seasonal apple and cinnamon sourdough, available Thursday and Friday only. Made with locally grown Bramley apples and our signature slow-ferment dough. Visit us at our Mansfield town centre bakery, open 7:30am to 5pm. Limited loaves — we'd recommend getting in early!",
            default => "Fresh posts daily at {$businessName}.",
        };
    }
}
