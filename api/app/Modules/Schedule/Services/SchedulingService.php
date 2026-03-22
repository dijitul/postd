<?php

namespace App\Modules\Schedule\Services;

use App\Models\Business;
use App\Models\Post;
use Carbon\Carbon;

class SchedulingService
{
    /**
     * Optimal posting times per platform (UK timezone, 24h).
     *
     * Based on platform-specific engagement research for UK audiences.
     * These are the ranked windows — we pick the next available slot.
     */
    private const OPTIMAL_TIMES = [
        'facebook' => [
            // Tuesday, Wednesday, Thursday peak. 9am-1pm best.
            'preferred_days' => [2, 3, 4], // 1=Mon, 7=Sun
            'time_slots' => ['09:00', '12:00', '15:00', '18:00'],
            'avoid_time_range' => ['22:00', '07:00'], // quiet hours
        ],
        'instagram' => [
            'preferred_days' => [2, 3, 5, 6], // Tue, Wed, Fri, Sat
            'time_slots' => ['08:00', '12:00', '17:00', '19:00', '21:00'],
            'avoid_time_range' => ['23:00', '07:00'],
        ],
        'twitter' => [
            'preferred_days' => [2, 3, 4, 5], // Tue-Fri
            'time_slots' => ['09:00', '12:00', '15:00', '18:00', '21:00'],
            'avoid_time_range' => ['23:00', '07:00'],
        ],
        'linkedin' => [
            // LinkedIn is strongly weekday-focused
            'preferred_days' => [2, 3, 4], // Tue, Wed, Thu
            'time_slots' => ['07:30', '12:00', '17:30'],
            'avoid_time_range' => ['19:00', '07:00'],
        ],
        'tiktok' => [
            'preferred_days' => [2, 4, 5, 6, 7], // Tue, Thu, Fri, Sat, Sun
            'time_slots' => ['07:00', '12:00', '19:00', '21:00'],
            'avoid_time_range' => ['23:00', '06:00'],
        ],
        'google_business_profile' => [
            // GBP posts are less time-sensitive — morning on weekdays works well
            'preferred_days' => [1, 2, 3, 4, 5], // Mon-Fri
            'time_slots' => ['09:00', '12:00', '14:00'],
            'avoid_time_range' => ['20:00', '08:00'],
        ],
    ];

    /**
     * Minimum gap between posts on the same platform (in minutes).
     */
    private const MIN_GAP_MINUTES = [
        'facebook' => 360,  // 6 hours
        'instagram' => 480, // 8 hours
        'twitter' => 120,   // 2 hours
        'linkedin' => 480,  // 8 hours
        'tiktok' => 480,    // 8 hours
        'google_business_profile' => 1440, // 24 hours
    ];

    /**
     * Get the next optimal posting slot for a given business and platform.
     *
     * Respects:
     * - Platform-optimal times (from research)
     * - Business custom time windows (from settings)
     * - Minimum gap between existing scheduled posts
     * - Quiet hours
     */
    public function getNextSlot(Business $business, string $platform): Carbon
    {
        $timezone = 'Europe/London';
        $now = Carbon::now($timezone);
        $settings = $business->settings;

        $platformConfig = self::OPTIMAL_TIMES[$platform] ?? self::OPTIMAL_TIMES['facebook'];
        $minGap = self::MIN_GAP_MINUTES[$platform] ?? 360;

        // Start from now, advance to the next valid slot
        $candidate = $now->copy()->addMinutes(30); // don't schedule too immediately

        // Look up to 14 days ahead
        $maxDaysAhead = 14;
        $searched = 0;

        while ($searched < ($maxDaysAhead * 24 * 2)) { // max iterations safety
            $searched++;

            // Check if this candidate slot is in quiet hours
            if ($this->isInQuietHours($candidate, $platformConfig)) {
                $candidate->addMinutes(30);
                continue;
            }

            // Check if within business time window (if configured)
            if ($settings && $settings->post_time_windows) {
                $dayName = strtolower($candidate->englishDayOfWeek);
                $window = $settings->getTimeWindowForDay($dayName);

                if ($window) {
                    if (! $this->isWithinWindow($candidate, $window)) {
                        $candidate->addMinutes(30);
                        continue;
                    }
                }
            }

            // Find the nearest optimal time slot for this day
            $optimalSlot = $this->snapToOptimalSlot($candidate, $platformConfig);

            // Check if there's already a post scheduled within the minimum gap
            if ($this->hasConflictingPost($business, $platform, $optimalSlot, $minGap)) {
                $candidate = $optimalSlot->copy()->addMinutes($minGap);
                continue;
            }

            return $optimalSlot;
        }

        // Fallback: schedule 1 hour from now if no optimal slot found
        return $now->copy()->addHour()->startOfHour();
    }

    /**
     * Get the next N slots for a platform (for bulk scheduling).
     */
    public function getNextNSlots(Business $business, string $platform, int $count): array
    {
        $slots = [];
        $lastSlot = null;

        for ($i = 0; $i < $count; $i++) {
            $slot = $this->getNextSlotAfter($business, $platform, $lastSlot);
            $slots[] = $slot;
            $lastSlot = $slot;
        }

        return $slots;
    }

    /**
     * Check whether a given datetime falls within a business's time window.
     *
     * @param array{start: string, end: string} $window
     */
    private function isWithinWindow(Carbon $dt, array $window): bool
    {
        $start = Carbon::parse($dt->format('Y-m-d').' '.$window['start'], 'Europe/London');
        $end = Carbon::parse($dt->format('Y-m-d').' '.$window['end'], 'Europe/London');

        if ($end->lessThan($start)) {
            // Window spans midnight
            return $dt->greaterThanOrEqualTo($start) || $dt->lessThanOrEqualTo($end);
        }

        return $dt->between($start, $end);
    }

    /**
     * Check if the candidate time falls within platform quiet hours.
     *
     * @param array{avoid_time_range: array{0: string, 1: string}} $config
     */
    private function isInQuietHours(Carbon $dt, array $config): bool
    {
        [$avoidFrom, $avoidUntil] = $config['avoid_time_range'];

        $from = Carbon::parse($dt->format('Y-m-d').' '.$avoidFrom, 'Europe/London');
        $until = Carbon::parse($dt->format('Y-m-d').' '.$avoidUntil, 'Europe/London');

        // Handle overnight quiet periods
        if ($from->greaterThan($until)) {
            return $dt->greaterThanOrEqualTo($from) || $dt->lessThanOrEqualTo($until);
        }

        return $dt->between($from, $until);
    }

    /**
     * Snap a candidate time to the nearest optimal time slot on the same day.
     */
    private function snapToOptimalSlot(Carbon $candidate, array $config): Carbon
    {
        $slots = $config['time_slots'];
        $date = $candidate->format('Y-m-d');
        $timezone = 'Europe/London';

        $bestSlot = null;
        foreach ($slots as $time) {
            $slot = Carbon::parse("{$date} {$time}", $timezone);

            // Skip slots in the past
            if ($slot->isPast()) {
                continue;
            }

            // Skip slots before the candidate time
            if ($slot->lessThan($candidate)) {
                continue;
            }

            if ($bestSlot === null || $slot->lessThan($bestSlot)) {
                $bestSlot = $slot;
            }
        }

        // If no slot found today, move to next preferred day
        if ($bestSlot === null) {
            return $this->getFirstSlotOnNextPreferredDay($candidate, $config);
        }

        return $bestSlot;
    }

    private function getFirstSlotOnNextPreferredDay(Carbon $from, array $config): Carbon
    {
        $preferredDays = $config['preferred_days'];
        $firstSlot = $config['time_slots'][0];
        $timezone = 'Europe/London';

        // Look through next 7 days for a preferred day
        for ($i = 1; $i <= 7; $i++) {
            $day = $from->copy()->addDays($i);
            $dayOfWeek = $day->isoWeekday(); // 1 = Mon, 7 = Sun

            if (in_array($dayOfWeek, $preferredDays)) {
                return Carbon::parse($day->format('Y-m-d').' '.$firstSlot, $timezone);
            }
        }

        // Fallback to tomorrow at first slot time
        $tomorrow = $from->copy()->addDay();
        return Carbon::parse($tomorrow->format('Y-m-d').' '.$firstSlot, $timezone);
    }

    /**
     * Check if there's already a post scheduled within $gapMinutes of the proposed slot.
     */
    private function hasConflictingPost(Business $business, string $platform, Carbon $slot, int $gapMinutes): bool
    {
        $from = $slot->copy()->subMinutes($gapMinutes);
        $to = $slot->copy()->addMinutes($gapMinutes);

        return Post::where('business_id', $business->id)
            ->where('platform', $platform)
            ->whereIn('status', [Post::STATUS_SCHEDULED, Post::STATUS_APPROVED])
            ->whereBetween('scheduled_at', [$from, $to])
            ->exists();
    }

    private function getNextSlotAfter(Business $business, string $platform, ?Carbon $after): Carbon
    {
        if ($after) {
            $minGap = self::MIN_GAP_MINUTES[$platform] ?? 360;
            // Create a temporary fake "conflict" at the last slot to force moving forward
            $business->posts()->create([
                'platform' => $platform,
                'content' => '__placeholder__',
                'status' => Post::STATUS_SCHEDULED,
                'scheduled_at' => $after,
                'requires_approval' => false,
            ]);
        }

        $slot = $this->getNextSlot($business, $platform);

        // Clean up placeholder if we created one
        if ($after) {
            $business->posts()
                ->where('content', '__placeholder__')
                ->where('scheduled_at', $after)
                ->delete();
        }

        return $slot;
    }
}
