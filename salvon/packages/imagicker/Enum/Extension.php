<?php

declare(strict_types=1);

namespace Salvon\Imagicker\Enum;

use ValueError;
use Salvon\Imagicker\Exception\UnsupportedExtension;

enum Extension: string
{
    public const EXIF_SUPPORTING_FORMATS = [
        self::JPEG,
        self::TIF,
        self::TIFF,
    ];

    /**
     * @throws UnsupportedExtension
     */
    public static function cast(string $value): self
    {
        $value = strtolower($value);

        if ($value === 'jpeg') {
            $value = self::JPEG->value;
        }

        try {
            return Extension::from($value);
        } catch (ValueError) {
            throw new UnsupportedExtension();
        }
    }

    case JPEG = 'jpg';
    case PNG = 'png';
    case WEBP = 'webp';
    case GIF = 'gif';
    case BMP = 'bmp';
    case TIF = 'tif';
    //Same as above, used interchangeably, more preferable in most cases (depends on operating system)
    //Package base extension for image/tiff file operations, .tif will be converted into .tiff
    case TIFF = 'tiff';
    case SVG = 'svg';
    case RAW = 'raw';
    case HEIC = 'heic';
}
