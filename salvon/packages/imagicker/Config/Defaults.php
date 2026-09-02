<?php

declare(strict_types=1);

namespace Salvon\Imagicker\Config;

use Salvon\Imagicker\Enum\ThumbnailSize;

final class Defaults
{
    public static function thumbDimensionBySize(ThumbnailSize $size): int
    {
        return self::thumbSizes()[$size->value];
    }

    public static function thumbSizes(): array
    {
        return [
            ThumbnailSize::XS->value => 50,
            ThumbnailSize::SM->value => 100,
            ThumbnailSize::MD->value => 200,
            ThumbnailSize::LG->value => 300,
            ThumbnailSize::XL->value => 400,
            ThumbnailSize::XXL->value => 500,
            ThumbnailSize::XXXL->value => 600,
            ThumbnailSize::XXXXL->value => 800,
        ];
    }

    public static function quality(): int
    {
        return 90;
    }
}
