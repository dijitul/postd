<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_business(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/onboarding/business', [
            'name' => 'My Test Business',
            'industry' => 'retail',
            'website_url' => 'https://mytestbusiness.co.uk',
            'google_reviews_url' => 'https://g.page/r/test/review',
            'tone' => 'friendly',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'business'])
            ->assertJsonPath('business.name', 'My Test Business');

        $this->assertDatabaseHas('businesses', [
            'user_id' => $user->id,
            'name' => 'My Test Business',
        ]);
    }

    public function test_onboarding_status_returns_correct_step(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/onboarding/status');

        $response->assertOk()
            ->assertJsonPath('step', 'business_setup')
            ->assertJsonPath('completed', false);
    }

    public function test_industries_endpoint_returns_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/onboarding/industries');

        $response->assertOk()
            ->assertJsonStructure(['industries']);

        $this->assertNotEmpty($response->json('industries'));
    }

    public function test_cannot_complete_onboarding_without_platforms(): void
    {
        $user = User::factory()->create();
        $user->businesses()->create([
            'name' => 'Test Business',
            'industry' => 'retail',
            'tone' => 'friendly',
        ]);

        $response = $this->actingAs($user)->postJson('/api/onboarding/complete');

        $response->assertStatus(422)
            ->assertJsonPath('error', 'no_platforms_connected');
    }
}
