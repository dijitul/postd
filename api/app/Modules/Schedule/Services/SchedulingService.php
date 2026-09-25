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
        'google_business_profile' => [
            // GBP posts are less time-sensitive — morning on weekdays works well
            'preferred_days' => [1, 2, 3, 4, 5], // Mon-Fri
            'time_slots' => ['09:00', '12:00', '14:00'],
            'avoid_time_range' => ['20:00', '08:00'],
        ],
    ];

    /**
     * Every-other-day spacing, used for any cadence of up to 4 a week.
     *
     * The old per-platform gaps were short enough to let two posts land on the
     * same day whatever the business had asked for, which is what made a feed
     * read as automated. At the default cadence a platform still gets at most
     * one post every other day.
     */
    private const EVERY_OTHER_DAY_MINUTES = 2880;

    /**
     * Minimum gap between two posts on one platform, from its weekly cadence.
     *
     * The single source for spacing: getNextSlot() enforces it and
     * ContentGenerationService uses it to work out how many posts a week can
     * physically hold. A fixed 48 hours capped every platform at 4 a week,
     * which made Growth's 7 and Agency's 14 impossible to deliver, so the gap
     * now narrows only as far as the cadence the business chose needs:
     *
     *  - up to 4 a week: 48 hours, exactly as before
     *  - 5 to 7 a week: 20 hours, so one a day. Each platform's time slots span
     *    less than 20 hours of a day, so two can never land on the same day,
     *    while the slot can still move between morning and afternoon.
     *  - 8 to 14 a week: 7 hours, so two a day. From a morning slot the next
     *    post lands in the late afternoon or evening slot, and 7 hours after
     *    that is past the day's last slot, so it moves to the next morning and
     *    a third never squeezes in.
     *
     * Quiet hours and time windows are applied on top by getNextSlot(). GBP is
     * held to 7 a week by the plans, so it never uses the tightest gap.
     */
    public static function minGapMinutesFor(int $postsPerWeek): int
    {
        return match (true) {
            $postsPerWeek <= 4 => self::EVERY_OTHER_DAY_MINUTES,
            $postsPerWeek <= 7 => 20 * 60,
            default => 7 * 60,
        };
    }

    /**
     * Get the next optimal posting slot for a given business and platform.
     *
     * Respects:
     * - Platform-optimal times (from research)
     * - Business custom time windows (from settings)
     * - Minimum gap between existing scheduled posts
     * - Quiet hours
     *
     * $postsPerWeek is the cadence the plan actually allows. Without it the
     * business's saved setting is used, which is what callers outside
     * generation want.
     */
    public function getNextSlot(Business $business, string $platform, ?int $postsPerWeek = null): Carbon
    {
        $timezone = 'Europe/London';
        $now = Carbon::now($timezone);
        $settings = $business->settings;

        $platformConfig = self::OPTIMAL_TIMES[$platform] ?? self::OPTIMAL_TIMES['facebook'];
        $postsPerWeek ??= $settings?->getPostsPerWeekForPlatform($platform) ?? 3;
        $minGap = self::minGapMinutesFor($postsPerWeek);

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

            // Find the nearest optimal time slot for this day. A cadence of more
            // than every other day cannot skip to the platform's preferred days
            // when today's slots run out, or a 7 a week target would lose most
            // weekends and Mondays, so it moves to tomorrow instead.
            $everyDay = $minGap < self::EVERY_OTHER_DAY_MINUTES;
            $optimalSlot = $this->snapToOptimalSlot($candidate, $platformConfig, $everyDay);

            // Check if there's already a post scheduled within the minimum gap
            $conflict = $this->conflictingPostTime($business, $platform, $optimalSlot, $minGap);

            if ($conflict) {
                // Move on from the post that clashed, not from the slot we tried.
                // Advancing by a full gap from the candidate overshot, so a Monday
                // evening post pushed Wednesday's all the way out to Friday.
                $candidate = $conflict->copy()->addMinutes($minGap);
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
     *
     * With $everyDay, a candidate past the day's last slot goes to tomorrow's
     * first slot rather than the next preferred day.
     */
    private function snapToOptimalSlot(Carbon $candidate, array $config, bool $everyDay = false): Carbon
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

        // If no slot found today, move to next preferred day, or simply to
        // tomorrow when the cadence needs every day
        if ($bestSlot === null) {
            if ($everyDay) {
                $tomorrow = $candidate->copy()->addDay();

                return Carbon::parse($tomorrow->format('Y-m-d').' '.$slots[0], $timezone);
            }

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
     * When the nearest already-scheduled post sits closer than $gapMinutes to the
     * proposed slot, return its time. Null means the slot is free.
     *
     * The bounds are exclusive: two posts exactly $gapMinutes apart are the spacing
     * we are aiming for, not a clash. Inclusive bounds rejected them and pushed the
     * cadence out by an extra day each time round.
     *
     * Pending posts count. They already carry a scheduled_at, and ignoring them let
     * the next run book the same afternoon all over again.
     */
    private function conflictingPostTime(Business $business, string $platform, Carbon $slot, int $gapMinutes): ?Carbon
    {
        $from = $slot->copy()->subMinutes($gapMinutes);
        $to = $slot->copy()->addMinutes($gapMinutes);

        $conflict = Post::where('business_id', $business->id)
            ->where('platform', $platform)
            ->whereIn('status', [
                Post::STATUS_PENDING,
                Post::STATUS_APPROVED,
                Post::STATUS_SCHEDULED,
                Post::STATUS_DISPATCHING,
                // A post that already went out occupies its slot most of all.
                // Leaving it out let today's post land hours after yesterday's.
                Post::STATUS_POSTED,
            ])
            ->where('scheduled_at', '>', $from)
            ->where('scheduled_at', '<', $to)
            ->orderBy('scheduled_at', 'desc')
            ->value('scheduled_at');

        return $conflict ? Carbon::parse($conflict)->setTimezone('Europe/London') : null;
    }

    private function getNextSlotAfter(Business $business, string $platform, ?Carbon $after): Carbon
    {
        if ($after) {
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
