<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Comped accounts: full access without a Stripe subscription.
            // Used for partners, beta testers and goodwill accounts.
            $table->string('comped_plan')->nullable()->after('trial_ends_at');
            $table->timestamp('comped_at')->nullable()->after('comped_plan');
            $table->timestamp('comped_until')->nullable()->after('comped_at');
            $table->uuid('comped_by')->nullable()->after('comped_until');
            $table->text('comp_note')->nullable()->after('comped_by');

            $table->index('comped_plan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['comped_plan']);
            $table->dropColumn(['comped_plan', 'comped_at', 'comped_until', 'comped_by', 'comp_note']);
        });
    }
};
