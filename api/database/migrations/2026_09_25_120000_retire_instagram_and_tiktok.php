<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * postd no longer publishes to Instagram or TikTok. Switch off any existing
     * connections and fail their unpublished posts with a readable reason, so
     * nothing sits in the queue waiting for a platform that will never answer.
     */
    public function up(): void
    {
        $retired = [
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
        ];

        DB::table('social_connections')
            ->whereIn('platform', array_keys($retired))
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        foreach ($retired as $platform => $name) {
            DB::table('posts')
                ->where('platform', $platform)
                ->whereIn('status', ['pending', 'approved', 'scheduled', 'dispatching'])
                ->update([
                    'status' => 'failed',
                    'failure_reason' => "{$name} is no longer supported by postd.",
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Not reversible: the platform integrations no longer exist.
    }
};
