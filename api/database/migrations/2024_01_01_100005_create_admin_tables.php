<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_health_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('connection_id')->nullable()->constrained('social_connections')->nullOnDelete();
            $table->string('check_type'); // token_validity, posting_test, webhook_receipt, api_rate_limit
            $table->string('status'); // ok, warning, error, critical
            $table->text('message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index('business_id');
            $table->index('connection_id');
            $table->index('check_type');
            $table->index('status');
            $table->index('checked_at');
        });

        // Webhook event log — every Stripe webhook received
        Schema::create('webhook_calls', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // event type e.g. customer.subscription.updated
            $table->jsonb('payload')->nullable();
            $table->text('exception')->nullable();
            $table->timestamp('created_at');

            $table->index('name');
            $table->index('created_at');
        });

        // Notifications log for admin to see what was sent
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // welcome, trial_ending, post_failed, weekly_summary
            $table->string('channel'); // mail, database, slack
            $table->string('status'); // sent, failed, queued
            $table->jsonb('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('webhook_calls');
        Schema::dropIfExists('system_health_logs');
    }
};
