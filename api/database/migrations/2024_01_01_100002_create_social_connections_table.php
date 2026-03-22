<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_connections', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // facebook, instagram, twitter, linkedin, tiktok, google_business_profile
            $table->text('access_token'); // encrypted at rest via Laravel encryption cast
            $table->text('refresh_token')->nullable(); // encrypted at rest
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('token_refresh_attempted')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->jsonb('scopes')->nullable(); // OAuth scopes granted
            $table->jsonb('raw_token_data')->nullable(); // full token response for debugging
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'platform']);
            $table->index('platform');
            $table->index('is_active');
            $table->index('expires_at');
        });

        Schema::create('platform_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('connection_id')->constrained('social_connections')->cascadeOnDelete();
            $table->string('platform_account_id'); // the ID on the social platform (e.g. Facebook page ID)
            $table->string('account_name');
            $table->string('account_type'); // page, profile, organisation, business, channel
            $table->string('account_url')->nullable();
            $table->string('avatar_url')->nullable();
            $table->boolean('is_selected')->default(true); // which account to post to if multiple
            $table->jsonb('metadata')->nullable(); // platform-specific data (follower count, category, etc.)
            $table->timestamps();

            $table->index('connection_id');
            $table->index('platform_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_accounts');
        Schema::dropIfExists('social_connections');
    }
};
