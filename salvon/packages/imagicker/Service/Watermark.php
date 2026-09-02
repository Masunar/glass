<?php

namespace Salvon\Imagicker\Service;

use Imagick;
use ImagickException;
use Salvon\Imagicker\Enum\Position;

final readonly class Watermark
{
    /**
     * @throws ImagickException
     */
    public static function add(Imagick $image, Imagick $watermark, Position $startingPoint = Position::TOP_LEFT, int $offsetX = 10, int $offsetY = 10, ?float $scaleOfBaseImage = null): void
    {
        if (is_float($scaleOfBaseImage)) {
            self::rescaleWatermarkToImage($image, $watermark, $scaleOfBaseImage);
        }

        $mainImageWidth = $image->getImageWidth();
        $mainImageHeight = $image->getImageHeight();
        $watermarkWidth = $watermark->getImageWidth();
        $watermarkHeight = $watermark->getImageHeight();

        $x = $offsetX;
        $y = $offsetY;

        switch ($startingPoint) {
            case Position::TOP_CENTER:
                $x = (($mainImageWidth - $watermarkWidth) / 2) - $offsetX;
                break;
            case Position::TOP_RIGHT:
                $x = $mainImageWidth - $watermarkWidth - $offsetX;
                break;
            case Position::MIDDLE_LEFT:
                $y = (($mainImageHeight - $watermarkHeight) / 2) - $offsetY;
                break;
            case Position::MIDDLE_CENTER:
                $x = (($mainImageWidth - $watermarkWidth) / 2) - $offsetX;
                $y = (($mainImageHeight - $watermarkHeight) / 2) - $offsetY;
                break;
            case Position::MIDDLE_RIGHT:
                $x = $mainImageWidth - $watermarkWidth - $offsetX;
                $y = (($mainImageHeight - $watermarkHeight) / 2) - $offsetY;
                break;
            case Position::BOTTOM_LEFT:
                $y = $mainImageHeight - $watermarkHeight - $offsetY;
                break;
            case Position::BOTTOM_CENTER:
                $x = (($mainImageWidth - $watermarkWidth) / 2) - $offsetX;
                $y = $mainImageHeight - $watermarkHeight - $offsetY;
                break;
            case Position::BOTTOM_RIGHT:
                $x = $mainImageWidth - $watermarkWidth - $offsetX;
                $y = $mainImageHeight - $watermarkHeight - $offsetY;
                break;
            case Position::TOP_LEFT:
            default:
                break;
        }

        $image->compositeImage($watermark, Imagick::COMPOSITE_OVER, (int) $x, (int) $y);
    }

    /**
     * @throws ImagickException
     */
    private static function rescaleWatermarkToImage(Imagick $image, Imagick $watermark, float $scale): void
    {
        $dimension = Determine::dimensions($image);

        $scaledWidth = $dimension->width * $scale;
        $newDimensions = Determine::newDimensions(file: $watermark, newWidth: $scaledWidth, oversize: true);

        $watermark->resizeImage(
            $newDimensions->width,
            $newDimensions->height,
            Imagick::FILTER_LANCZOS,
            1,
        );
    }
}
