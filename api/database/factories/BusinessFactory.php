<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessFactory extends Factory
{
    public function definition(): array
    {
        $industries = ['restaurant', 'retail', 'trades', 'professional_services', 'health_beauty', 'automotive'];
        $tones = ['professional', 'friendly', 'casual'];
        $cities = ['Mansfield', 'Nottingham', 'Derby', 'Sheffield', 'Leicester', 'Birmingham'];

        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'industry' => fake()->randomElement($industries),
            'website_url' => 'https://'.fake()->domainName(),
            'google_reviews_url' => null,
            'tone' => fake()->randomElement($tones),
            'city' => fake()->randomElement($cities),
            'postcode' => fake()->postcode(),
            'description' => fake()->paragraph(),
            'onboarding_complete' => false,
            'is_active' => true,
        ];
    }

    public function onboarded(): static
    {
        return $this->state(fn (array $attributes) => [
            'onboarding_complete' => true,
            'onboarding_completed_at' => now()->subDays(rand(1, 30)),
        ]);
    }
}
