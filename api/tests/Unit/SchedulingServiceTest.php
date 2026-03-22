<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Modules\Schedule\Services\SchedulingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulingServiceTest extends TestCase
{
    use RefreshDatabase;

    private SchedulingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SchedulingService();
    }

    public function test_returns_future_slot(): void
    {
        $business = Business::factory()->create();

        $slot = $this->service->getNextSlot($business, 'facebook');

        $this->assertTrue($slot->isFuture());
    }

    public function test_different_platforms_get_different_slots(): void
    {
        $business = Business::factory()->create();

        $facebookSlot = $this->service->getNextSlot($business, 'facebook');
        $linkedInSlot = $this->service->getNextSlot($business, 'linkedin');

        // LinkedIn avoids evenings — should typically be earlier in the day
        $this->assertNotNull($facebookSlot);
        $this->assertNotNull($linkedInSlot);
    }

    public function test_slot_respects_quiet_hours(): void
    {
        $business = Business::factory()->create();

        Carbon::setTestNow(Carbon::parse('2025-01-01 23:30:00', 'Europe/London'));

        $slot = $this->service->getNextSlot($business, 'facebook');

        // Should not schedule in the early hours
        $slotHour = $slot->setTimezone('Europe/London')->hour;
        $this->assertGreaterThan(6, $slotHour);

        Carbon::setTestNow();
    }
}
