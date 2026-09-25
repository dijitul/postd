<?php

namespace Tests\Unit;

use App\Modules\Media\Exceptions\ImageRejected;
use App\Modules\Media\Services\ImageProcessor;
use PHPUnit\Framework\TestCase;

class ImageProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is not installed.');
        }
    }

    /**
     * A patchwork of large blocks, so it reads as a photo rather than a flat
     * block of colour. The blocks scale with the image because, like a real
     * photo, its structure has to survive being shrunk to the 16x16 sample.
     */
    private function photo(int $width, int $height, string $format = 'jpeg'): string
    {
        $image = imagecreatetruecolor($width, $height);
        $block = max(8, intdiv($width, 12));
        mt_srand(42);

        for ($x = 0; $x < $width; $x += $block) {
            for ($y = 0; $y < $height; $y += $block) {
                $colour = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
                imagefilledrectangle($image, $x, $y, $x + $block - 1, $y + $block - 1, $colour);
            }
        }

        return $this->encode($image, $format);
    }

    private function flat(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 240, 240, 240));

        return $this->encode($image, 'jpeg');
    }

    private function encode(\GdImage $image, string $format): string
    {
        ob_start();
        match ($format) {
            'png' => imagepng($image),
            'webp' => imagewebp($image),
            default => imagejpeg($image, null, 90),
        };

        return (string) ob_get_clean();
    }

    private function rejection(string $bytes): ?string
    {
        try {
            (new ImageProcessor())->process($bytes);
        } catch (ImageRejected $e) {
            return $e->reason;
        }

        return null;
    }

    public function test_size_rules(): void
    {
        $this->assertNull(ImageProcessor::sizeRejection(600, 400));
        $this->assertNull(ImageProcessor::sizeRejection(1200, 1800));
        $this->assertNull(ImageProcessor::sizeRejection(2200, 1000));

        $this->assertSame('too_small', ImageProcessor::sizeRejection(599, 800));
        $this->assertSame('too_small', ImageProcessor::sizeRejection(800, 399));
        // Wider than 2.2:1 is a banner strip.
        $this->assertSame('bad_shape', ImageProcessor::sizeRejection(2000, 800));
        // Taller than 1:2 is a phone screenshot.
        $this->assertSame('bad_shape', ImageProcessor::sizeRejection(600, 1300));
    }

    public function test_small_and_oddly_shaped_images_are_rejected(): void
    {
        $this->assertSame('too_small', $this->rejection($this->photo(300, 300)));
        $this->assertSame('bad_shape', $this->rejection($this->photo(1800, 600)));
    }

    public function test_flat_colour_is_rejected(): void
    {
        $this->assertSame('blank', $this->rejection($this->flat(1000, 700)));
    }

    public function test_non_images_and_heic_are_rejected(): void
    {
        $this->assertSame('unsupported', $this->rejection('<html>not an image</html>'));
        $this->assertSame('heic', $this->rejection("\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic"));
    }

    public function test_small_jpeg_is_kept_byte_for_byte(): void
    {
        $bytes = $this->photo(1000, 700);
        $result = (new ImageProcessor())->process($bytes);

        $this->assertSame($bytes, $result['bytes']);
        $this->assertSame([1000, 700], [$result['width'], $result['height']]);
        $this->assertNotNull($result['thumbnail']);
    }

    public function test_large_images_are_scaled_to_2048_and_png_becomes_jpeg(): void
    {
        $result = (new ImageProcessor())->process($this->photo(3000, 2000, 'png'));
        $info = getimagesizefromstring($result['bytes']);

        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
        $this->assertSame(2048, $result['width']);
        $this->assertSame(1365, $result['height']);

        $thumb = getimagesizefromstring($result['thumbnail']);
        $this->assertSame(ImageProcessor::THUMB_LONG_SIDE, max($thumb[0], $thumb[1]));
    }

    public function test_webp_is_converted_to_jpeg(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD has no WebP support.');
        }

        $result = (new ImageProcessor())->process($this->photo(900, 900, 'webp'));

        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($result['bytes'])[2]);
    }
}
