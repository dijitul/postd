<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('industry'); // Restaurant, Retail, Trades, Professional Services, Health & Beauty, etc.
            $table->string('website_url')->nullable();
            $table->string('google_reviews_url')->nullable();
            $table->string('google_place_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode', 10)->nullable();
            $table->string('country', 2)->default('GB');
            $table->enum('tone', ['professional', 'friendly', 'casual'])->default('friendly');
            $table->text('description')->nullable(); // AI-generated or manual business description
            $table->text('usp_notes')->nullable(); // Key selling points extracted from website
            $table->boolean('onboarding_complete')->default(false);
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('industry');
            $table->index('is_active');
            $table->index('onboarding_complete');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
