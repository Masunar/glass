<?php

namespace Salvon\Bundle\Media\Provider\Contract;

use Salvon\Bundle\Media\Model\Media;
use Salvon\Bundle\Media\Entity\MediaFile;

interface MediaProvider
{
    public static function get(Media $media): MediaFile;

    public static function getPath(Media $media): string;

    public static function store(MediaFile $mediaFile, bool $useCache = true): MediaFile;

    public static function delete(Media $media): void;

    public static function createCache(Media $media): void;

    public static function deleteCache(Media $media): void;
}
