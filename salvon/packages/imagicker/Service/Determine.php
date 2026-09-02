<?php

namespace Salvon\Imagicker\Service;

use Imagick;
use ImagickException;
use Salvon\Imagicker\DTO\Dimensions;
use Salvon\Imagicker\Enum\Orientation;

class Determine
{
    /**
     * @throws ImagickException
     */
    public static function orientation(Imagick $file): Orientation
    {
        if ($file->getImageWidth() > $file->getImageHeight()) {
            return Orientation::HORIZONTAL;
        }

        return Orientation::VERTICAL;
    }

    /**
     * @throws ImagickException
     */
    public static function dpi(Imagick $file): int
    {
        $horizontalDpi = $file->getImageResolution()['x'] ?? 0;
        $imageUnits = $file->getImageUnits();

        return (int) round(
            match ($imageUnits) {
                Imagick::RESOLUTION_PIXELSPERCENTIMETER => $horizontalDpi * 2.54,
                default => $horizontalDpi,
            },
        );
    }

    /**
     * @throws ImagickException
     */
    public static function dimensions(Imagick $file): Dimensions
    {
        $width = $file->getImageWidth();
        $height = $file->getImageHeight();

        return new Dimensions($width, $height);
    }

    /**
     * @throws ImagickException
     */
    public static function newDimensions(Imagick $file, ?int $newWidth = null, ?int $newHeight = null, bool $oversize = false): Dimensions
    {
        $originalWidth = $file->getImageWidth();
        $originalHeight = $file->getImageHeight();

        return DimensionsCalculator::calculate($originalWidth, $originalHeight, $newWidth, $newHeight, $oversize);
    }
}
