<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel Cashier subscriptions (managed by Cashier, but we define here)
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('billable');
            $table->string('type');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['billable_id', 'billable_type']);
        });

        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_id')->unique();
            $table->string('stripe_product')->nullable();
            $table->string('stripe_price');
            $table->integer('quantity')->nullable();
            $table->timestamps();
        });

        // Plan feature matrix
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->string('plan_name')->unique(); // starter, growth, pro
            $table->string('display_name');
            $table->unsignedInteger('price_pence'); // price in GBP pence ex-VAT
            $table->unsignedTinyInteger('platform_limit')->nullable(); // null = unlimited
            $table->boolean('tiktok_included')->default(false);
            $table->boolean('gbp_included')->default(true);
            $table->boolean('ai_images_included')->default(true);
            $table->unsignedSmallInteger('posts_per_month')->nullable(); // null = unlimited
            $table->boolean('approval_workflow')->default(true);
            $table->boolean('analytics_included')->default(true);
            $table->boolean('priority_support')->default(false);
            $table->string('stripe_price_id')->nullable();
            $table->jsonb('features_list')->nullable(); // marketing-facing feature list
            $table->timestamps();
        });

        // AI cost tracking — for admin overview and billing analysis
        Schema::create('ai_cost_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operation'); // text_generation, image_generation, transcription
            $table->string('model'); // gpt-4o, gpt-4o-mini, dall-e-3
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0); // cost in USD
            $table->timestamps();

            $table->index('business_id');
            $table->index('operation');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_cost_logs');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
    }
};
