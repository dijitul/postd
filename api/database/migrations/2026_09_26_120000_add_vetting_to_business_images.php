<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each library photo actually shows, and whether it is fit for a post.
 *
 * The first import of a real business's photos brought in website
 * screenshots, a text graphic and two copies of the same photo, all of which
 * would have gone out on posts. Each photo is now looked at once (ImageVetter):
 * kind and description come from that, vetting_note says why a photo was
 * switched off, and perceptual_hash catches near-duplicates that differ in
 * size or compression, which content_hash cannot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_images', function (Blueprint $table) {
            $table->string('kind', 20)->nullable()->after('google_category');
            $table->text('description')->nullable()->after('kind');
            $table->timestamp('vetted_at')->nullable()->after('description');
            $table->string('vetting_note', 40)->nullable()->after('vetted_at');
            $table->string('perceptual_hash', 16)->nullable()->after('content_hash');
            $table->index(['business_id', 'vetted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('business_images', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'vetted_at']);
            $table->dropColumn(['kind', 'description', 'vetted_at', 'vetting_note', 'perceptual_hash']);
        });
    }
};
