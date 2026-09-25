<?php

namespace Tests\Unit;

use App\Modules\Media\Services\ImagePicker;
use App\Modules\Media\Services\ImageProcessor;
use App\Modules\Media\Services\ImageVetter;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ImageVettingTest extends TestCase
{
    // ── Reading the vetting reply ──────────────────────────────────────────

    public function test_a_photo_is_usable_and_described(): void
    {
        $verdict = ImageVetter::parse('{"kind": "photo", "usable": true, "description": "A dog sitting in an office."}');

        $this->assertSame('photo', $verdict['kind']);
        $this->assertTrue($verdict['usable']);
        $this->assertSame('A dog sitting in an office.', $verdict['description']);
        $this->assertNull($verdict['note']);
    }

    public function test_a_screenshot_is_switched_off_with_a_reason(): void
    {
        $verdict = ImageVetter::parse('Here you go: {"kind": "screenshot", "usable": false, "description": "A web page."}');

        $this->assertFalse($verdict['usable']);
        $this->assertSame(ImageVetter::NOTE_SCREENSHOT, $verdict['note']);
    }

    public function test_usable_is_never_trusted_for_a_kind_we_do_not_post(): void
    {
        $verdict = ImageVetter::parse('{"kind": "text_graphic", "usable": true, "description": "A slide of words."}');

        $this->assertFalse($verdict['usable']);
        $this->assertSame(ImageVetter::NOTE_TEXT, $verdict['note']);
    }

    public function test_an_unknown_kind_or_garbage_is_not_usable(): void
    {
        $this->assertFalse(ImageVetter::parse('{"kind": "meme", "usable": true}')['usable']);
        $this->assertNull(ImageVetter::parse('I cannot help with that.'));
    }

    // ── Near-duplicates ────────────────────────────────────────────────────

    public function test_a_resized_copy_is_a_near_duplicate_and_a_different_photo_is_not(): void
    {
        $original = $this->gradientPhoto(800, 600, flip: false);
        $resized = imagescale($original, 400, 300);
        $different = $this->gradientPhoto(800, 600, flip: true);

        $a = ImageProcessor::perceptualHash($original);
        $b = ImageProcessor::perceptualHash($resized);
        $c = ImageProcessor::perceptualHash($different);

        $this->assertSame(16, strlen($a));
        $this->assertTrue(ImageProcessor::isNearDuplicate($a, $b));
        $this->assertFalse(ImageProcessor::isNearDuplicate($a, $c));
        $this->assertFalse(ImageProcessor::isNearDuplicate($a, null));
    }

    // ── Matching a photo to the post ───────────────────────────────────────

    public function test_the_photo_matching_the_post_wins_over_an_unrelated_one(): void
    {
        $images = [
            $this->image('dog', 'A brown dog sitting in front of a red sign.'),
            $this->image('desk', 'A laptop and notebook on a desk with a website design sketch.'),
        ];

        $chosen = (new ImagePicker())->choose(
            $images,
            null,
            CarbonImmutable::parse('2026-09-26'),
            fn (int $min) => $min,
            'Your website design is the first thing customers see. We sketch every layout in a notebook first.'
        );

        $this->assertSame('desk', $chosen['id']);
    }

    public function test_unvetted_and_screenshot_photos_are_never_picked(): void
    {
        $images = [
            $this->image('unvetted', 'A desk.', vetted: false),
            $this->image('screenshot', 'A web page.', kind: 'screenshot'),
        ];

        $this->assertNull((new ImagePicker())->choose($images, null, CarbonImmutable::parse('2026-09-26')));
    }

    /** @return array<string, mixed> */
    private function image(string $id, string $description, bool $vetted = true, string $kind = 'photo'): array
    {
        return [
            'id' => $id,
            'source' => 'google',
            'page_url' => null,
            'is_enabled' => true,
            'last_used_at' => null,
            'vetted' => $vetted,
            'kind' => $kind,
            'description' => $description,
        ];
    }

    /** A left-to-right gradient with a block of detail, or its mirror image. */
    private function gradientPhoto(int $width, int $height, bool $flip): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);

        for ($x = 0; $x < $width; $x++) {
            $shade = (int) (255 * ($flip ? $width - $x : $x) / $width);
            imageline($image, $x, 0, $x, $height, imagecolorallocate($image, $shade, $shade, $shade));
        }

        imagefilledrectangle($image, (int) ($width * 0.3), (int) ($height * 0.3), (int) ($width * 0.5), (int) ($height * 0.6), imagecolorallocate($image, 20, 20, 20));

        return $image;
    }
}
