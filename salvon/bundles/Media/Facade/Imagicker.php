<?php

namespace Salvon\Bundle\Media\Facade;

use ImagickException;
use Salvon\Bundle\Media\Config\Config;
use Salvon\Imagicker\Enum\Extension;
use Salvon\Imagicker\Exception\UnsupportedExtension;
use Salvon\Imagicker\Exception\OpeningFailedException;
use Salvon\Imagicker\Service\Imagicker as SalvonImagicker;

final class Imagicker
{
    /**
     * @throws OpeningFailedException
     */
    public static function create(string $path): SalvonImagicker
    {
        return new SalvonImagicker($path, Config::getThumbSizes(), Config::getThumbQuality());
    }

    /**
     * @throws ImagickException
     * @throws UnsupportedExtension
     * @throws OpeningFailedException
     */
    public static function getExtension(string $path, bool $asString = false): Extension|string
    {
        return (new SalvonImagicker($path, Config::getThumbSizes(), Config::getThumbQuality()))->extension($asString);
    }
}
