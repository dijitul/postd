<?php

namespace Tests\Unit;

use App\Modules\Media\Services\ImagePicker;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ImagePickerTest extends TestCase
{
    private Carbon $now;

    protected function setUp(): void
    {
        $this->now = Carbon::parse('2026-09-25 09:00:00');
    }

    private function image(string $id, array $overrides = []): array
    {
        return $overrides + [
            'id' => $id,
            'source' => 'website',
            'page_url' => null,
            'is_enabled' => true,
            'last_used_at' => null,
        ];
    }

    private function pick(array $images, ?string $page = null, ?callable $random = null): ?string
    {
        // No shuffling unless a test asks for it, so ties keep their given order.
        $random ??= fn (int $min, int $max) => $max;

        return (new ImagePicker())->choose($images, $page, $this->now, $random)['id'] ?? null;
    }

    public function test_returns_null_for_an_empty_or_fully_disabled_library(): void
    {
        $this->assertNull($this->pick([]));
        $this->assertNull($this->pick([$this->image('a', ['is_enabled' => false])]));
    }

    public function test_never_picks_ai_images(): void
    {
        $this->assertNull($this->pick([$this->image('ai', ['source' => 'ai'])]));
        $this->assertSame('real', $this->pick([
            $this->image('ai', ['source' => 'ai']),
            $this->image('real', ['last_used_at' => $this->now->copy()->subDay()]),
        ]));
    }

    public function test_prefers_a_photo_from_the_page_the_post_was_written_from(): void
    {
        $this->assertSame('boilers', $this->pick([
            $this->image('home', ['page_url' => 'https://smith.co.uk/']),
            $this->image('google', ['source' => 'google']),
            $this->image('boilers', ['page_url' => 'https://www.smith.co.uk/services/boilers/']),
        ], 'https://smith.co.uk/services/boilers'));
    }

    public function test_then_prefers_google_and_uploads_over_other_website_photos(): void
    {
        $this->assertSame('google', $this->pick([
            $this->image('home', ['page_url' => 'https://smith.co.uk/']),
            $this->image('google', ['source' => 'google']),
        ], 'https://smith.co.uk/services/boilers'));

        $this->assertSame('upload', $this->pick([
            $this->image('home', ['page_url' => 'https://smith.co.uk/']),
            $this->image('upload', ['source' => 'upload']),
        ]));
    }

    public function test_skips_photos_used_in_the_last_21_days_when_another_is_available(): void
    {
        $this->assertSame('rested', $this->pick([
            $this->image('same-page-recent', [
                'page_url' => 'https://smith.co.uk/boilers',
                'last_used_at' => $this->now->copy()->subDays(3),
            ]),
            $this->image('rested', ['last_used_at' => $this->now->copy()->subDays(30)]),
        ], 'https://smith.co.uk/boilers'));
    }

    public function test_reuses_the_least_recently_used_when_everything_is_recent(): void
    {
        $this->assertSame('older', $this->pick([
            $this->image('newer', ['last_used_at' => $this->now->copy()->subDays(2)]),
            $this->image('older', ['last_used_at' => $this->now->copy()->subDays(10)]),
        ]));
    }

    public function test_never_used_beats_least_recently_used(): void
    {
        $this->assertSame('fresh', $this->pick([
            $this->image('old', ['last_used_at' => $this->now->copy()->subDays(90)]),
            $this->image('fresh'),
        ]));
    }

    public function test_ties_are_broken_by_the_random_source(): void
    {
        $images = [$this->image('a'), $this->image('b'), $this->image('c')];

        // Fisher-Yates with j always 0 rotates the list, putting "b" first.
        $this->assertSame('b', $this->pick($images, null, fn (int $min, int $max) => 0));
        $this->assertSame('a', $this->pick($images, null, fn (int $min, int $max) => $max));
    }

    public function test_url_normalisation(): void
    {
        $this->assertSame(
            ImagePicker::normaliseUrl('https://smith.co.uk/services/boilers'),
            ImagePicker::normaliseUrl('HTTP://www.Smith.co.uk/services/boilers/?utm_source=x')
        );
    }
}
