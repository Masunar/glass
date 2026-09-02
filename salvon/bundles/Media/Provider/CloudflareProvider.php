<?php

namespace Salvon\Bundle\Media\Provider;

use Override;
use Exception;
use JsonException;
use ImagickException;
use Salvon\Service\Storage;
use Salvon\Api\HasGuzzleClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Salvon\Bundle\Media\Model\Media;
use Salvon\Bundle\Media\Config\Config;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Salvon\Bundle\Media\Enum\MediaType;
use Salvon\Bundle\Media\Entity\MediaFile;
use Salvon\Bundle\Media\Facade\Imagicker;
use Salvon\Bundle\Media\Exception\CannotStoreMedia;
use Salvon\Bundle\Media\Exception\CannotDeleteMedia;
use Salvon\Bundle\Media\Provider\Contract\MediaProvider;
use Salvon\Imagicker\Exception\UnsupportedExtension;
use Salvon\Imagicker\Exception\OpeningFailedException;
use Salvon\Bundle\Media\Exception\CloudflareCredentialsEmpty;
use Salvon\Bundle\Media\Enum\MediaProvider as MediaProviderEnum;

class CloudflareProvider implements MediaProvider
{
    use HasGuzzleClient;

    protected static MediaProviderEnum $provider = MediaProviderEnum::CLOUDFLARE;

    /**
     * @throws CloudflareCredentialsEmpty
     */
    #[Override]
    public static function get(Media $media): MediaFile
    {
        self::checkConfig();

        return new MediaFile(
            provider: $media->provider,
            mediaType: $media->media_type,
            uploaded: $media->created_at,
            location: $media->location,
            name: $media->name,
            mimeType: $media->mime_type,
            metadata: $media->metadata,
            variants: self::getVariants($media),
            uuid: $media->uuid,
            id: $media->id,
            url: self::prepareUrl($media),
        );
    }

    /**
     * @param Media $media
     * @return string
     * @throws GuzzleException
     * @throws CloudflareCredentialsEmpty
     */
    #[Override]
    public static function getPath(Media $media): string
    {
        self::checkConfig();

        $request = static::guzzleClient()->get(
            self::getCloudflareUrl() . '/' . $media->location . '/blob',
            [
                ...self::headers(),
            ],
        );

        $content = $request->getBody()->getContents();
        $filePath = sprintf('media/%s/%s_%s.%s', $media->uuid, $media->location, $media->name, $media->extension);
        Storage::media()->put($filePath, $content);

        return Storage::media()->path($filePath);
    }

    /**
     * @param MediaFile $mediaFile
     * @param bool $useCache
     * @return MediaFile
     * @throws CloudflareCredentialsEmpty
     * @throws GuzzleException
     * @throws JsonException
     * @throws ImagickException
     * @throws OpeningFailedException
     * @throws UnsupportedExtension
     * @throws CannotStoreMedia
     */
    #[Override]
    public static function store(MediaFile $mediaFile, bool $useCache = true): MediaFile
    {
        self::checkConfig();

        $extension = Imagicker::getExtension($mediaFile->location);

        try {
            DB::beginTransaction();
            $request = static::guzzleClient()->post(self::getCloudflareUrl(), [
                ...self::headers(),
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen($mediaFile->location, 'rb'),
                        'filename' => $mediaFile->name !== null && $mediaFile->name !== '' && $mediaFile->name !== '0' ? $mediaFile->name : basename($mediaFile->location),
                    ],
                ],
            ]);

            $response = (array) json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            /** @var Media $media */
            $media = Media::modelQuery()->create([
                'provider' => self::$provider,
                'media_type' => MediaType::IMAGE,
                'location' => $response['result']['id'],
                'name' => $mediaFile->name,
                'mime_type' => mime_content_type($mediaFile->location),
                'metadata' => $mediaFile->metadata,
                'extension' => $extension,
                'uuid' => $response['result']['id'],
                'checksum' => md5_file($mediaFile->location),
            ]);

            DB::commit();
        } catch (ClientException $exception) {
            if ($exception->getResponse() !== 200) {
                DB::rollBack();
                throw new CannotStoreMedia();
            }
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
            variants: self::getVariants($media),
            uuid: $media->uuid,
            id: $media->id,
        );
    }

    public static function getVariants(Media $media): array
    {
        $result = [];
        foreach (array_keys(Config::getAvailableVariants()) as $key) {
            $result[$key] = self::prepareUrl($media, $key);
        }

        return $result;
    }

    public static function prepareUrl(Media $media, ?string $variant = null): string
    {
        if ($variant === null) {
            return sprintf(
                'https://imagedelivery.net/%s/%s',
                Config::getCloudflareAccountHash(),
                $media->location,
            );
        }

        return sprintf(
            'https://imagedelivery.net/%s/%s/%s',
            Config::getCloudflareAccountHash(),
            $media->location,
            $variant,
        );
    }

    /**
     * @throws GuzzleException
     */
    public static function getContentByUuid(string $uuid): string
    {
        $request = static::guzzleClient()->get(
            self::getCloudflareUrl() . '/' . $uuid . '/blob',
            [
                ...self::headers(),
            ],
        );

        return $request->getBody()->getContents();
    }

    /**
     * @throws GuzzleException
     */
    public static function getChecksumByUuid(string $uuid): string
    {
        return md5(self::getContentByUuid($uuid));
    }

    /**
     * @throws GuzzleException
     * @throws CannotDeleteMedia
     */
    #[Override]
    public static function delete(Media $media): void
    {
        $media->delete();
        try {
            static::guzzleClient()->delete(self::getCloudflareUrl() . '/' . $media->location, [
                ...self::headers(),
            ]);
        } catch (ClientException $clientException) {
            if ($clientException->getResponse() !== 200) {
                DB::rollBack();
                Log::error($clientException);
                throw new CannotDeleteMedia();
            }
        }
    }

    #[Override]
    public static function createCache(Media $media): void {}

    #[Override]
    public static function deleteCache(Media $media): void {}

    private static function getCloudflareUrl(): string
    {
        return sprintf('https://api.cloudflare.com/client/v4/accounts/%s/images/v1', Config::getCloudflareAccountID());
    }

    private static function headers(): array
    {
        return ['headers' => [
            'Authorization' => 'Bearer ' . Config::getCloudflareApiToken(),
        ]];
    }

    /**
     * @throws CloudflareCredentialsEmpty
     */
    private static function checkConfig(): void
    {
        if (Config::checkIsCloudflareCredentialsNull()) {
            throw new CloudflareCredentialsEmpty();
        }
    }
}
