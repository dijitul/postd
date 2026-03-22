<?php

namespace Database\Seeders;

use App\Models\PlanFeature;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed plan features
        $this->call(PlanFeaturesSeeder::class);

        // Create admin user for Dijitul team
        if (! User::where('email', config('app.admin_email', 'admin@dijitul.co.uk'))->exists()) {
            User::create([
                'name' => 'Dijitul Admin',
                'email' => config('app.admin_email', 'admin@dijitul.co.uk'),
                'password' => Hash::make(env('ADMIN_INITIAL_PASSWORD', 'change-me-now!')),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]);

            $this->command->info('Admin user created.');
        }

        // In local/testing environments, create a demo business
        if (app()->isLocal()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
