<?php

namespace Salvon\Bundle\Media\Provider;

use Override;
use Exception;
use Throwable;
use ImagickException;
use Salvon\Service\Str;
use Salvon\Service\Encoder;
use Salvon\Service\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Salvon\Bundle\Media\Model\Media;
use Salvon\Bundle\Media\Config\Config;
use Salvon\Bundle\Media\Entity\MediaFile;
use Salvon\Bundle\Media\Facade\Imagicker;
use Salvon\Imagicker\Exception\OverwriteException;
use Salvon\Imagicker\Exception\UnsupportedExtension;
use Salvon\Imagicker\Exception\OpeningFailedException;
use Salvon\Bundle\Media\Provider\Contract\MediaProvider;
use Salvon\Bundle\Media\Enum\MediaProvider as MediaProviderEnum;

class Base64Provider implements MediaProvider
{
    protected static MediaProviderEnum $provider = MediaProviderEnum::BASE64;

    #[Override]
    public static function get(Media $media): MediaFile
    {
        return new MediaFile(
            provider: $media->provider,
            mediaType: $media->media_type,
            uploaded: $media->created_at,
            location: $media->location,
            name: $media->name,
            mimeType: $media->mime_type,
            metadata: $media->metadata,
            variants: self::getUrls($media),
            id: $media->id,
        );
    }

    /**
     * @param MediaFile $mediaFile
     * @param bool $useCache
     * @return MediaFile
     * @throws ImagickException
     * @throws OpeningFailedException
     * @throws OverwriteException
     * @throws UnsupportedExtension
     * @throws Throwable
     */
    #[Override]
    public static function store(MediaFile $mediaFile, bool $useCache = true): MediaFile
    {
        $extension = Imagicker::getExtension($mediaFile->location, true);

        try {
            DB::beginTransaction();

            /** @var Media $media */
            $media = Media::modelQuery()->create([
                'provider' => $mediaFile->provider,
                'location' => Encoder::base64(file_get_contents($mediaFile->location)),
                'name' => $mediaFile->name,
                'mime_type' => $mediaFile->mimeType ?? mime_content_type($mediaFile->location),
                'extension' => $extension,
                'metadata' => $mediaFile->metadata,
                'uuid' => Str::uuid(),
                'media_type' => mime_content_type($mediaFile->location),
                'checksum' => md5_file($mediaFile->location),
            ]);

            if ($useCache) {
                self::createCache($media);
            }

            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

        return new MediaFile(
            provider: $media->provider,
            mediaType: $media->media_type,
            uploaded: $media->created_at,
            location: $media->location,
            name: $media->name,
            mimeType: $media->mime_type,
            metadata: $media->metadata,
            uuid: $media->uuid,
            id: $media->id,
        );
    }

    #[Override]
    public static function getPath(Media $media): string
    {
        $path = sprintf('media/%s-%s', $media->uuid, $media->name);

        Storage::media()->put($path, Encoder::fromBase64($media->location));

        return Storage::media()->path($path);
    }

    #[Override]
    public static function delete(Media $media): void
    {
        self::deleteCache($media);
        $media->delete();
    }

    public static function getUrls(Media $media): array
    {
        $toReturn = [];
        foreach (array_keys(Config::getAvailableVariants()) as $key) {
            $toReturn[$key] = URL::route('media_get-media', [$media->uuid, $key]);
        }

        return $toReturn;
    }

    /**
     * @throws ImagickException
     * @throws OverwriteException
     * @throws OpeningFailedException
     * @throws UnsupportedExtension
     */
    #[Override]
    public static function createCache(Media $media): void
    {
        $path = sprintf('media/%s', $media->uuid);
        Storage::media()->makeDirectory($path);

        Storage::media()->put($path . '/original', Encoder::fromBase64($media->location));

        foreach (Config::getAvailableVariants() as $key => $variant) {
            $imagicker = Imagicker::create(Storage::media()->path($path . '/original'));
            $imagicker->thumbnail($variant, Config::getThumbQuality());
            $imagicker->save(Storage::media()->path($path . '/' . $key));
        }

        Storage::media()->delete($path . '/original');
    }

    #[Override]
    public static function deleteCache(Media $media): void
    {
        $path = sprintf('media/%s', $media->uuid);
        Storage::media()->deleteDirectory($path);
    }
}
