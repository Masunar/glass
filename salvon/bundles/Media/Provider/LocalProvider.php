<?php

namespace Salvon\Bundle\Media\Provider;

use Override;
use Exception;
use Throwable;
use Carbon\Carbon;
use ImagickException;
use Salvon\Service\Str;
use Salvon\Service\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Salvon\Bundle\Media\Model\Media;
use Salvon\Bundle\Media\Config\Config;
use Salvon\Bundle\Media\Enum\MediaType;
use Salvon\Bundle\Media\Entity\MediaFile;
use Salvon\Bundle\Media\Facade\Imagicker;
use Salvon\Bundle\Media\Provider\Contract\MediaProvider;
use Salvon\Imagicker\Exception\OverwriteException;
use Salvon\Imagicker\Exception\UnsupportedExtension;
use Salvon\Imagicker\Exception\OpeningFailedException;
use Salvon\Bundle\Media\Enum\MediaProvider as MediaProviderEnum;

class LocalProvider implements MediaProvider
{
    protected static MediaProviderEnum $provider = MediaProviderEnum::LOCAL;

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
            url: self::getUrl($media),
        );
    }

    #[Override]
    public static function getPath(Media $media): string
    {
        return $media->location;
    }

    public static function getUrls(Media $media): array
    {
        $toReturn = [];

        if ($media->media_type === MediaType::IMAGE) {
            foreach (array_keys(Config::getAvailableVariants()) as $key) {
                $toReturn[$key] = self::absoluteRoute('media_get-media-variant', [$media->uuid, $key]);
            }

            return $toReturn;
        }

        return $toReturn;
    }

    public static function getUrl(Media $media): string
    {
        return self::absoluteRoute('media_get-media', [$media->uuid]);
    }

    private static function absoluteRoute(string $name, array $params): string
    {
        return rtrim((string) config('app.url'), '/') . URL::route($name, $params, false);
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    #[Override]
    public static function store(MediaFile $mediaFile, bool $useCache = true): MediaFile
    {
        $now = Carbon::now();
        $uuid = Str::uuid();

        $extension = pathinfo($mediaFile->location, PATHINFO_EXTENSION);

        if ($extension === 'pdf') {
            $mediaFile->mediaType = MediaType::PDF;
        }

        if ($mediaFile->mediaType === MediaType::IMAGE) {
            $extension = Imagicker::getExtension($mediaFile->location, true);
        }

        $location = sprintf('media/%s/%s/%s/%s.%s', $now->year, $now->format('m'), $now->format('d'), $uuid->toString(), $extension);

        try {
            DB::beginTransaction();

            /** @var Media $media */
            $media = Media::modelQuery()->create([
                'provider' => $mediaFile->provider,
                'media_type' => $mediaFile->mediaType,
                'location' => Storage::disk()->path($location),
                'name' => $mediaFile->name,
                'mime_type' => mime_content_type($mediaFile->location),
                'extension' => $extension,
                'metadata' => $mediaFile->metadata,
                'uuid' => $uuid,
                'checksum' => md5_file($mediaFile->location),
            ]);

            Storage::disk()->put($location, file_get_contents($mediaFile->location));

            if ($useCache) {
                self::createCache($media);
            }

            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();

            Storage::disk()->delete($location);
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
            variants: self::getUrls($media),
            uuid: $media->uuid,
            id: $media->id,
        );
    }

    #[Override]
    public static function delete(Media $media): void
    {
        $path = self::getPath($media);
        self::deleteCache($media);
        $media->delete();
        unlink($path);
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
        if ($media->media_type !== MediaType::IMAGE) {
            return;
        }

        $path = sprintf('media/%s', $media->uuid);
        Storage::media()->makeDirectory($path);

        foreach (Config::getAvailableVariants() as $key => $variant) {
            $imagicker = Imagicker::create($media->location);
            $imagicker->thumbnail($variant, Config::getThumbQuality());
            $imagicker->save(Storage::media()->path($path . '/' . $key));
        }
    }

    #[Override]
    public static function deleteCache(Media $media): void
    {
        $path = sprintf('media/%s', $media->uuid);
        Storage::media()->deleteDirectory($path);
    }
}
