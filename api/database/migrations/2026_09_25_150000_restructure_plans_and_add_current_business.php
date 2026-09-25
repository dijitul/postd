<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Local / Growth / Agency plan structure.
 *
 * - Starter is now Local. Comps saved as 'starter' are renamed so the admin
 *   screens and reporting agree; PlanCatalogue also aliases the old key, so a
 *   stray value elsewhere still resolves. 'pro' is left alone: it remains a
 *   legacy plan with Agency entitlements at its original price.
 * - plan_features is dropped. It duplicated the plan list, drifted from the
 *   real limits and was never read; config/plans.php is the one source now.
 * - users.current_business_id records which location an Agency account is
 *   working on. See User::business().
 * - Cadence defaults. Facebook defaulted to 5 a week and X to 10, but the old
 *   48 hour spacing held every platform to 4, so 4 is what those accounts
 *   actually received. Now that paid plans can go to 7 and 14, rows still on
 *   those untouched defaults are set to 4 so nobody's feed suddenly gets
 *   busier, and new rows start at 3, matching Local.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('comped_plan', 'starter')->update(['comped_plan' => 'local']);

        Schema::dropIfExists('plan_features');

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('current_business_id')->nullable()->after('comp_note');
            $table->index('current_business_id');
        });

        DB::table('business_settings')->where('posts_per_week_facebook', 5)->update(['posts_per_week_facebook' => 4]);
        DB::table('business_settings')->where('posts_per_week_twitter', 10)->update(['posts_per_week_twitter' => 4]);

        Schema::table('business_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('posts_per_week_facebook')->default(3)->change();
            $table->unsignedTinyInteger('posts_per_week_twitter')->default(3)->change();
        });
    }

    public function down(): void
    {
        // Row values set to 4 above are left as they are: they match what those
        // accounts were actually getting.
        Schema::table('business_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('posts_per_week_facebook')->default(5)->change();
            $table->unsignedTinyInteger('posts_per_week_twitter')->default(10)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['current_business_id']);
            $table->dropColumn('current_business_id');
        });

        DB::table('users')->where('comped_plan', 'local')->update(['comped_plan' => 'starter']);

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->string('plan_name')->unique();
            $table->string('display_name');
            $table->unsignedInteger('price_pence');
            $table->unsignedTinyInteger('platform_limit')->nullable();
            $table->boolean('tiktok_included')->default(false);
            $table->boolean('gbp_included')->default(true);
            $table->boolean('ai_images_included')->default(true);
            $table->unsignedSmallInteger('posts_per_month')->nullable();
            $table->boolean('approval_workflow')->default(true);
            $table->boolean('analytics_included')->default(true);
            $table->boolean('priority_support')->default(false);
            $table->string('stripe_price_id')->nullable();
            $table->jsonb('features_list')->nullable();
            $table->timestamps();
        });
    }
};
