<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_admin' => false,
            'trial_ends_at' => now()->addDays(14),
            'referral_code' => Str::upper(Str::random(8)),

            // A real users row has these, so a factory user must too. Without
            // them the model comes back missing attributes that hasActivePlan()
            // and activePlanName() read, and strict mode (on everywhere but
            // production) throws rather than treating them as null.
            'comped_plan' => null,
            'comped_at' => null,
            'comped_until' => null,
            'comped_by' => null,
            'comp_note' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    public function trialExpired(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => now()->subDay(),
        ]);
    }
}
