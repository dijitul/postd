<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('business_id')->unique()->constrained()->cascadeOnDelete();

            // Post approval settings
            $table->boolean('auto_approve_posts')->default(false); // true = fully automatic
            $table->unsignedTinyInteger('approval_window_hours')->default(24); // hours before auto-approve if not reviewed

            // Posting schedule — stored as jsonb
            // Format: {"monday": {"start": "09:00", "end": "18:00"}, ...} or null for any time
            $table->jsonb('post_time_windows')->nullable();

            // Per-platform custom settings — stored as jsonb
            // Format: {"facebook": {"enabled": true, "posts_per_week": 5}, ...}
            $table->jsonb('platform_settings')->nullable();

            // Content preferences
            $table->jsonb('content_themes')->nullable(); // custom themes to focus on
            $table->jsonb('content_exclusions')->nullable(); // topics/phrases to avoid
            $table->boolean('include_local_news_hooks')->default(true);
            $table->boolean('include_review_content')->default(true);
            $table->boolean('generate_images')->default(true);
            $table->boolean('generate_tiktok_videos')->default(false);

            // Posting frequency targets per platform
            $table->unsignedTinyInteger('posts_per_week_facebook')->default(5);
            $table->unsignedTinyInteger('posts_per_week_instagram')->default(7);
            $table->unsignedTinyInteger('posts_per_week_twitter')->default(10);
            $table->unsignedTinyInteger('posts_per_week_linkedin')->default(3);
            $table->unsignedTinyInteger('posts_per_week_tiktok')->default(3);
            $table->unsignedTinyInteger('posts_per_week_gbp')->default(3);

            // Notification preferences
            $table->boolean('notify_post_approved')->default(true);
            $table->boolean('notify_post_failed')->default(true);
            $table->boolean('notify_weekly_summary')->default(true);
            $table->boolean('notify_token_expiring')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
