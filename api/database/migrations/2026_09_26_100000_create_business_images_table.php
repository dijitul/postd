<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The photo library each business's posts draw their images from.
 *
 * Real photos come from the business's own website, its Google Business
 * Profile and direct uploads. AI images are recorded here too so the owner
 * can see and switch them off, but they are never picked for reuse.
 *
 * content_hash is unique per business so the same photo is never stored
 * twice, however many pages or sources it turns up on.
 *
 * businesses.images_google_synced_at records the last attempt to read the
 * Google Business Profile media list, successful or not. The GBP API quota is
 * tiny, so that call is made at most once a week per business.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_images', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20); // website | google | upload | ai
            $table->text('source_url')->nullable();
            $table->text('page_url')->nullable();
            $table->string('storage_path');
            $table->text('url');
            $table->string('thumbnail_path')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('content_hash', 40);
            $table->string('google_media_name')->nullable();
            $table->string('google_category', 40)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'content_hash']);
            $table->index(['business_id', 'source']);
            $table->index(['business_id', 'created_at']);
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->timestamp('images_google_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('images_google_synced_at');
        });

        Schema::dropIfExists('business_images');
    }
};
