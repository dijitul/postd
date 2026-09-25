<?php

namespace App\Modules\Media\Services;

use App\Modules\Media\Exceptions\ImageRejected;

/**
 * Turns downloaded or uploaded bytes into a photo fit for a social post.
 *
 * Every platform we publish to takes a JPEG, and a JPEG of at most 2048px on
 * the long side is comfortably inside every platform's size limits, so that is
 * what everything becomes. A JPEG already within the limit and the right way up
 * is kept byte for byte: re-encoding it would only cost quality.
 *
 * No network and no storage here, so the rules can be tested on their own.
 */
class ImageProcessor
{
    // Smaller than this looks soft or pixelated once a platform scales it up.
    public const MIN_WIDTH = 600;
    public const MIN_HEIGHT = 400;

    // Taller than 1:2 or wider than 2.2:1 is a banner strip or a phone
    // screenshot, and every platform crops it badly.
    public const MIN_ASPECT = 0.5;
    public const MAX_ASPECT = 2.2;

    public const MAX_LONG_SIDE = 2048;
    public const JPEG_QUALITY = 85;

    // Thumbnails for the Photos page grid, so a phone is not pulling down
    // dozens of full size photos just to show a page of small squares.
    public const THUMB_LONG_SIDE = 480;
    public const THUMB_QUALITY = 78;

    // Decoding costs about 5 bytes a pixel. 40 megapixels is ~200MB, which is
    // already past what a queue worker should spend on one photo.
    public const MAX_PIXELS = 40_000_000;

    // Luminance spread below which an image is a flat colour: a placeholder,
    // a blank hero behind text, a solid backdrop.
    private const MIN_LUMA_SPREAD = 6.0;

    /**
     * Why a photo of these displayed dimensions is not usable, or null if it is.
     */
    public static function sizeRejection(int $width, int $height): ?string
    {
        if ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT) {
            return 'too_small';
        }

        $aspect = $width / max(1, $height);

        if ($aspect < self::MIN_ASPECT || $aspect > self::MAX_ASPECT) {
            return 'bad_shape';
        }

        return null;
    }

    /** Friendly words for a rejection code, for the upload response. */
    public static function reasonText(string $code): string
    {
        return match ($code) {
            'too_small' => 'That photo is too small. Please use one at least '.self::MIN_WIDTH.' pixels wide and '.self::MIN_HEIGHT.' pixels tall.',
            'bad_shape' => 'That photo is too tall and thin, or too long and narrow, to look right in a post. Try a more standard shaped photo.',
            'too_large' => 'That photo has too many pixels for us to process. Please upload a smaller copy.',
            'heic' => 'iPhone HEIC photos cannot be read yet. Please share the photo as a JPEG (or set your iPhone camera to Most Compatible) and upload it again.',
            'blank' => 'That image looks like a plain block of colour rather than a photo.',
            default => 'That file is not a photo we can use. Please upload a JPEG, PNG or WebP.',
        };
    }

    /**
     * @return array{bytes: string, width: int, height: int, thumbnail: string|null}
     *
     * @throws ImageRejected
     */
    public function process(string $bytes): array
    {
        if ($this->looksLikeHeic($bytes)) {
            throw new ImageRejected('heic');
        }

        $info = @getimagesizefromstring($bytes);

        if (! $info || ! in_array($info[2], $this->supportedTypes(), true)) {
            throw new ImageRejected('unsupported');
        }

        [$width, $height, $type] = $info;

        if ($width * $height > self::MAX_PIXELS) {
            throw new ImageRejected('too_large');
        }

        $orientation = $type === IMAGETYPE_JPEG ? $this->exifOrientation($bytes) : 1;

        // Orientations 5 to 8 are a quarter turn, so the photo displays with
        // its sides swapped, and it is the displayed shape that has to pass.
        [$shownWidth, $shownHeight] = $orientation >= 5 ? [$height, $width] : [$width, $height];

        if ($reason = self::sizeRejection($shownWidth, $shownHeight)) {
            throw new ImageRejected($reason);
        }

        $image = $this->decode($bytes, $type);

        if (! $image) {
            throw new ImageRejected('unsupported');
        }

        try {
            $image = $this->applyOrientation($image, $orientation);

            if ($this->isNearlyBlank($image)) {
                throw new ImageRejected('blank');
            }

            $keepOriginal = $type === IMAGETYPE_JPEG
                && $orientation === 1
                && max($width, $height) <= self::MAX_LONG_SIDE;

            if ($keepOriginal) {
                $out = $bytes;
            } else {
                $image = $this->fitWithin($image, self::MAX_LONG_SIDE);
                $out = $this->encodeJpeg($image, self::JPEG_QUALITY);
            }

            $finalWidth = imagesx($image);
            $finalHeight = imagesy($image);

            $thumbnail = null;
            if (max($finalWidth, $finalHeight) > self::THUMB_LONG_SIDE) {
                $thumb = $this->fitWithin($image, self::THUMB_LONG_SIDE, copy: true);
                $thumbnail = $this->encodeJpeg($thumb, self::THUMB_QUALITY);
                imagedestroy($thumb);
            }

            return [
                'bytes' => $out,
                'width' => $finalWidth,
                'height' => $finalHeight,
                'thumbnail' => $thumbnail,
            ];
        } finally {
            imagedestroy($image);
        }
    }

    /** @return int[] */
    private function supportedTypes(): array
    {
        $types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

        if (defined('IMAGETYPE_AVIF') && function_exists('imagecreatefromavif')) {
            $types[] = IMAGETYPE_AVIF;
        }

        return $types;
    }

    /**
     * HEIC is an ISO box file with an "ftyp" brand of heic, heix, mif1 and so
     * on. GD cannot read it, so it is spotted up front to give a useful message.
     */
    private function looksLikeHeic(string $bytes): bool
    {
        return substr($bytes, 4, 4) === 'ftyp'
            && in_array(substr($bytes, 8, 4), ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'mif1', 'msf1'], true);
    }

    private function decode(string $bytes, int $type): ?\GdImage
    {
        // imagecreatefromstring cannot sniff AVIF on every GD build, so AVIF
        // goes through a temporary file and the dedicated reader.
        if (defined('IMAGETYPE_AVIF') && $type === IMAGETYPE_AVIF) {
            $tmp = tempnam(sys_get_temp_dir(), 'avif');

            try {
                file_put_contents($tmp, $bytes);
                $image = @imagecreatefromavif($tmp);
            } finally {
                @unlink($tmp);
            }
        } else {
            $image = @imagecreatefromstring($bytes);
        }

        if (! $image) {
            return null;
        }

        // Transparent PNGs and WebPs go onto white, not the black GD would give.
        if ($type !== IMAGETYPE_JPEG) {
            $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagedestroy($image);
            $image = $flat;
        }

        return $image;
    }

    /**
     * The EXIF orientation of a JPEG, 1 when it has none.
     *
     * Phones store photos sideways and set this flag rather than rotating the
     * pixels. Browsers honour it, but GD drops EXIF on re-encoding, so any photo
     * we resize has to be turned the right way up first or it goes out sideways.
     */
    private function exifOrientation(string $bytes): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $orientation = (int) ($exif['Orientation'] ?? 1);

        return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    private function applyOrientation(\GdImage $image, int $orientation): \GdImage
    {
        if ($orientation === 1) {
            return $image;
        }

        // imagerotate turns anticlockwise; 270 is a quarter turn clockwise.
        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);

            if ($rotated) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        // The mirrored variants are the plain rotation plus a horizontal flip:
        // 2 is a mirror, 4 a half turn mirrored, 5 and 7 a quarter turn mirrored.
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        return $image;
    }

    /**
     * Scale down so the long side is at most $maxSide. Never scales up.
     */
    private function fitWithin(\GdImage $image, int $maxSide, bool $copy = false): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longSide = max($width, $height);

        if ($longSide <= $maxSide) {
            if (! $copy) {
                return $image;
            }

            $clone = imagecreatetruecolor($width, $height);
            imagecopy($clone, $image, 0, 0, 0, 0, $width, $height);

            return $clone;
        }

        $scale = $maxSide / $longSide;
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        if (! $copy) {
            imagedestroy($image);
        }

        return $resized;
    }

    /**
     * Is this a flat block of colour rather than a photo?
     *
     * Shrinks to 16x16 and measures how much the brightness varies. Any real
     * photo, even a pale one, varies far more than a solid fill or a gradient-free
     * placeholder does. Cheap enough to run on every candidate.
     */
    private function isNearlyBlank(\GdImage $image): bool
    {
        $sample = imagecreatetruecolor(16, 16);
        imagecopyresampled($sample, $image, 0, 0, 0, 0, 16, 16, imagesx($image), imagesy($image));

        $values = [];
        for ($x = 0; $x < 16; $x++) {
            for ($y = 0; $y < 16; $y++) {
                $rgb = imagecolorat($sample, $x, $y);
                $values[] = 0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF);
            }
        }
        imagedestroy($sample);

        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / count($values);

        return sqrt($variance) < self::MIN_LUMA_SPREAD;
    }

    private function encodeJpeg(\GdImage $image, int $quality): string
    {
        // Progressive JPEGs show a rough preview while they load on a slow
        // connection instead of drawing from the top down.
        imageinterlace($image, true);

        ob_start();
        imagejpeg($image, null, $quality);

        return (string) ob_get_clean();
    }
}
