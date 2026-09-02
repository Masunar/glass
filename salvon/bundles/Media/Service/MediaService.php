<?php

namespace Salvon\Bundle\Media\Service;

use Exception;
use Salvon\Facade\Error;
use Salvon\Service\Storage;
use Illuminate\Support\Facades\Log;
use Salvon\Bundle\Media\Model\Media;
use Salvon\Bundle\Media\Config\Config;
use Salvon\Bundle\Media\Enum\MediaType;
use Salvon\Bundle\Media\Resolver\MediaResolver;
use Salvon\Imagicker\Exception\OpeningFailedException;

final class MediaService
{
    /**
     * @throws Exception
     */
    public function execute(Media $media, ?string $variant = null): string
    {
        if ($media->media_type === MediaType::IMAGE) {
            return $this->handleImage($media, $variant);
        }

        if ($media->media_type === MediaType::PDF) {
            return $this->handleFile($media);
        }

        Error::notFound();
    }

    /**
     * @throws Exception
     */
    private function handleImage(Media $media, ?string $variant): string
    {
        $variants = Config::getAvailableVariants();

        if (!array_key_exists($variant, $variants)) {
            $variant = array_key_last($variants);
        }

        try {
            $path = sprintf('media/%s/%s.%s', $media->uuid, $variant, $media->extension);
            $exists = Storage::media()->exists($path);
            if ($exists) {
                return Storage::media()->path($path);
            }

            MediaResolver::createCache($media);

            if (Storage::media()->exists($path)) {
                return Storage::media()->path($path);
            }

            Log::error('Media cache not created');
            Error::ise();
        } catch (OpeningFailedException $notFound) {
            Log::error($notFound);
            Error::notFound();
        } catch (Exception $exception) {
            Log::error($exception);
            Error::ise();
        }
    }

    private function handleFile(Media $media): string
    {
        return $media->location;
    }
}
