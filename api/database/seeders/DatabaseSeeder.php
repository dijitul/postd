<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Plans are no longer seeded: config/plans.php is the source of truth.

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
