<?php

namespace Tests\Unit;

use App\Modules\Schedule\Services\SchedulingService;
use PHPUnit\Framework\TestCase;

/**
 * SchedulingService::minGapMinutesFor(), checked against each platform's real
 * time slots with no database. SchedulingServiceTest covers slot placement
 * itself, which needs Postgres.
 */
class SchedulingSpacingTest extends TestCase
{
    private const WEEK_MINUTES = 7 * 24 * 60;

    /** @return array<string, array{time_slots: string[]}> */
    private function optimalTimes(): array
    {
        return (new \ReflectionClassConstant(SchedulingService::class, 'OPTIMAL_TIMES'))->getValue();
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }

    public function test_default_cadence_keeps_every_other_day_spacing(): void
    {
        foreach ([0, 1, 2, 3, 4] as $perWeek) {
            $this->assertSame(48 * 60, SchedulingService::minGapMinutesFor($perWeek), "{$perWeek} a week");
        }
    }

    public function test_a_week_can_hold_every_plans_cadence(): void
    {
        // Local 3, Growth 7, Agency 14, plus the in-between settings.
        foreach ([3, 4, 5, 7, 10, 14] as $perWeek) {
            $gap = SchedulingService::minGapMinutesFor($perWeek);
            $fits = intdiv(self::WEEK_MINUTES, $gap) + 1;

            $this->assertGreaterThanOrEqual($perWeek, $fits, "{$perWeek} a week needs room for {$perWeek} posts");
        }
    }

    public function test_spacing_never_tightens_as_cadence_drops(): void
    {
        $previous = PHP_INT_MAX;
        foreach (range(0, 14) as $perWeek) {
            $gap = SchedulingService::minGapMinutesFor($perWeek);
            $this->assertLessThanOrEqual($previous, $gap);
            $previous = $gap;
        }
    }

    public function test_up_to_seven_a_week_never_puts_two_on_one_day(): void
    {
        $gap = SchedulingService::minGapMinutesFor(7);

        foreach ($this->optimalTimes() as $platform => $config) {
            $slots = array_map(fn ($t) => $this->minutes($t), $config['time_slots']);
            $span = max($slots) - min($slots);

            $this->assertGreaterThan($span, $gap, "{$platform}: first to last slot is {$span} minutes");
        }
    }

    public function test_fourteen_a_week_allows_two_a_day_but_never_three(): void
    {
        $gap = SchedulingService::minGapMinutesFor(14);

        foreach ($this->optimalTimes() as $platform => $config) {
            // GBP is capped at 7 a week on every plan, and its slots all sit
            // between 9am and 2pm, so it never runs at this cadence.
            if ($platform === 'google_business_profile') {
                continue;
            }

            $slots = array_map(fn ($t) => $this->minutes($t), $config['time_slots']);
            sort($slots);

            // Snap forward to the first slot at or after a time on the same day.
            $snap = function (int $at) use ($slots): ?int {
                foreach ($slots as $slot) {
                    if ($slot >= $at) {
                        return $slot;
                    }
                }

                return null;
            };

            $secondFitsFromFirst = $snap($slots[0] + $gap) !== null;
            $this->assertTrue($secondFitsFromFirst, "{$platform}: a second post fits after the first slot");

            foreach ($slots as $first) {
                $second = $snap($first + $gap);
                if ($second === null) {
                    continue;
                }

                $this->assertNull($snap($second + $gap), "{$platform}: no third post after {$first} then {$second}");
            }
        }
    }
}
