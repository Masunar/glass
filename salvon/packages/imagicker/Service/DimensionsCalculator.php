<?php

declare(strict_types=1);

namespace Salvon\Imagicker\Service;

use Salvon\Imagicker\DTO\Dimensions;

class DimensionsCalculator
{
    public static function calculate(int $originalWidth, int $originalHeight, ?int $newWidth = null, ?int $newHeight = null, bool $oversize = false): Dimensions
    {
        //Empty parameters sent, keep original dimensions
        if (($newWidth === null || $newWidth === 0) && ($newHeight === null || $newHeight === 0)) {
            return new Dimensions($originalWidth, $originalHeight);
        }

        //Both parameters send, use them
        if ($newWidth && ($newHeight !== null && $newHeight !== 0)) {
            return self::fromDimensions($originalWidth, $originalHeight, $newWidth, $newHeight, $oversize);
        }

        //Only width sent, calculate height from original with proportions
        if ($newWidth !== null && $newWidth !== 0) {
            return self::fromWidth($originalWidth, $originalHeight, $newWidth, $oversize);
        }

        //Only height sent, calculate height from original with proportions
        return self::fromHeight($originalWidth, $originalHeight, $newHeight, $oversize);
    }

    protected static function fromDimensions(int $originalWidth, int $originalHeight, int $newWidth, int $newHeight, bool $oversize = false): Dimensions
    {
        //Oversize width, keep original
        if ($newWidth > $originalWidth && !$oversize) {
            $newWidth = $originalWidth;
        }

        //Oversize height, keep original
        if ($newHeight > $originalHeight && !$oversize) {
            $newHeight = $originalHeight;
        }

        return new Dimensions((int) round($newWidth), (int) round($newHeight));
    }

    protected static function fromWidth(int $originalWidth, int $originalHeight, int $newWidth, bool $oversize = false): Dimensions
    {
        if ($newWidth > $originalWidth && !$oversize) {
            $newWidth = $originalWidth;
        }

        $newHeight = self::calculateNewValue($originalHeight, $newWidth, $originalWidth);

        return new Dimensions((int) round($newWidth), $newHeight);
    }

    protected static function fromHeight(int $originalWidth, int $originalHeight, int $newHeight, bool $oversize = false): Dimensions
    {
        if ($newHeight > $originalHeight && !$oversize) {
            $newHeight = $originalHeight;
        }

        $newWidth = self::calculateNewValue($originalWidth, $newHeight, $originalHeight);
        return new Dimensions($newWidth, (int) round($newHeight));
    }

    protected static function calculateNewValue(int $original, int $newOpposite, int $originalOpposite): int
    {
        return (int) round($original * ($newOpposite / $originalOpposite));
    }
}
