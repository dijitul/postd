<?php

namespace App\Modules\Media\Exceptions;

use App\Modules\Media\Services\ImageProcessor;

/**
 * A downloaded or uploaded file that is not a usable photo.
 *
 * Expected and frequent during website imports (logos, thumbnails, banners),
 * so it carries a short code rather than being logged as an error.
 */
class ImageRejected extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(ImageProcessor::reasonText($reason));
    }
}
