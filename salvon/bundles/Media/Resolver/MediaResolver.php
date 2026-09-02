<?php

declare(strict_types=1);

namespace Salvon\Bundle\Media\Resolver;

use ReflectionException;
use Salvon\Bundle\Media\Model\Media;
use GuzzleHttp\Exception\GuzzleException;
use Salvon\Bundle\Media\Entity\MediaFile;
use Salvon\Bundle\Media\Enum\MediaProvider;
use Salvon\Bundle\Media\Provider\LocalProvider;
use Salvon\Bundle\Media\Provider\Base64Provider;
use Salvon\Bundle\Media\Provider\CloudflareProvider;
use Salvon\Bundle\Media\Exception\CloudflareCredentialsEmpty;

final class MediaResolver
{
    /**
     * @throws CloudflareCredentialsEmpty
     */
    public static function get(int $mediaId): MediaFile
    {
        /** @var Media $media */
        $media = Media::modelQuery()->findOrFail($mediaId);
        return self::matchProvider($media->provider)::get($media);
    }

    /**
     * @throws CloudflareCredentialsEmpty
     */
    public static function getByModel(Media $media): MediaFile
    {
        return self::matchProvider($media->provider)::get($media);
    }

    /**
     * @throws CloudflareCredentialsEmpty
     */
    public static function getMediaPathById(int $mediaId): string
    {
        /** @var Media $media */
        $media = Media::modelQuery()
            ->findOrFail($mediaId);

        return self::matchProvider($media->provider)::getPath($media);
    }

    /**
     * @throws CloudflareCredentialsEmpty
     */
    public static function getMediaPath(Media|int $media): string
    {
        if (is_int($media)) {
            /** @var Media $media */
            $media = Media::modelQuery()
                ->findOrFail($media);
        }

        return self::matchProvider($media->provider)::getPath($media);
    }

    /**
     * @throws ReflectionException
     * @throws CloudflareCredentialsEmpty
     */
    public static function store(MediaFile $mediaFile, bool $useCache = true): MediaFile
    {
        $provider = self::matchProvider($mediaFile->provider);

        try {
            $mediaFile = $provider::store($mediaFile, $useCache);
        } catch (ReflectionException $reflectionException) {
            throw new $reflectionException();
        }

        return $mediaFile;
    }

    /**
     * @throws GuzzleException
     * @throws CloudflareCredentialsEmpty
     */
    public static function delete(int $mediaId): void
    {
        /** @var Media $media */
        $media = Media::modelQuery()->findOrFail($mediaId);
        self::matchProvider($media->provider)::delete($media);
    }

    /**
     * @throws GuzzleException
     * @throws CloudflareCredentialsEmpty
     */
    public static function deleteByModel(Media $media): void
    {
        self::matchProvider($media->provider)::delete($media);
    }

    public static function createCache(Media $media): void
    {
        self::matchProvider($media->provider)::createCache($media);
    }

    public static function deleteCache(Media $media): void
    {
        self::matchProvider($media->provider)::deleteCache($media);
    }

    /**
     * @return class-string
     */
    private static function matchProvider(MediaProvider $provider): string
    {
        return match ($provider) {
            MediaProvider::LOCAL => LocalProvider::class,
            MediaProvider::CLOUDFLARE => CloudflareProvider::class,
            MediaProvider::BASE64 => Base64Provider::class,
        };
    }
}
