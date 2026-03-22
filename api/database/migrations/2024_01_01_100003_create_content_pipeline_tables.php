<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Raw data gathered from various sources before AI processing
        Schema::create('content_sources', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // review, website, news, manual
            $table->string('source_url')->nullable();
            $table->text('raw_data'); // the raw text/json scraped or fetched
            $table->jsonb('structured_data')->nullable(); // parsed/structured version
            $table->decimal('sentiment_score', 3, 2)->nullable(); // -1 to 1
            $table->boolean('processed')->default(false); // has been used to generate content
            $table->timestamp('scraped_at');
            $table->timestamps();

            $table->index('business_id');
            $table->index('type');
            $table->index('processed');
            $table->index('scraped_at');
        });

        // Content brief — the distilled insight before actual posts are written
        Schema::create('content_briefs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('source_id')->nullable()->constrained('content_sources')->nullOnDelete();
            $table->string('theme'); // e.g. "5-star review highlight", "opening hours reminder", "local news hook"
            $table->text('key_messages'); // what the posts should communicate
            $table->text('tone_notes')->nullable(); // any specific tone adjustments for this brief
            $table->string('source_type'); // derived from content_source.type or 'scheduled'
            $table->jsonb('reference_data')->nullable(); // any specific data to include (review text, news headline, etc.)
            $table->boolean('posts_generated')->default(false);
            $table->timestamp('posts_generated_at')->nullable();
            $table->timestamps();

            $table->index('business_id');
            $table->index('source_id');
            $table->index('posts_generated');
            $table->index('created_at');
        });

        // Individual posts — one per platform per brief
        Schema::create('posts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('brief_id')->nullable()->constrained('content_briefs')->nullOnDelete();
            $table->foreignUuid('connection_id')->nullable()->constrained('social_connections')->nullOnDelete();
            $table->string('platform'); // facebook, instagram, twitter, linkedin, tiktok, google_business_profile
            $table->text('content'); // the post text
            $table->text('content_edited')->nullable(); // if the user edits the content before approving
            $table->jsonb('media_urls')->nullable(); // array of media URLs (images/videos) on DO Spaces
            $table->jsonb('hashtags')->nullable(); // extracted/generated hashtags
            $table->string('status')->default('pending'); // pending, approved, rejected, scheduled, posted, failed
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('platform_post_id')->nullable(); // ID returned by the platform after posting
            $table->string('platform_post_url')->nullable(); // public URL of the post
            $table->boolean('requires_approval')->default(true);
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->jsonb('ai_metadata')->nullable(); // tokens used, model, prompt version
            $table->timestamps();
            $table->softDeletes();

            $table->index('business_id');
            $table->index('platform');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index('posted_at');
            $table->index(['business_id', 'status']);
            $table->index(['platform', 'scheduled_at']);
        });

        // Every posting attempt — for audit trail and debugging
        Schema::create('post_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('post_id')->constrained()->cascadeOnDelete();
            $table->timestamp('attempted_at');
            $table->boolean('succeeded')->default(false);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->string('error_type')->nullable(); // rate_limit, auth_error, content_policy, network, unknown
            $table->decimal('duration_ms', 8, 2)->nullable();
            $table->timestamps();

            $table->index('post_id');
            $table->index('attempted_at');
            $table->index('succeeded');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_attempts');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('content_briefs');
        Schema::dropIfExists('content_sources');
    }
};
