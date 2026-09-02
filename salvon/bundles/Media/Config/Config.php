<?php

namespace Salvon\Bundle\Media\Config;

use Salvon\Imagicker\Config\Defaults;
use Salvon\Imagicker\Enum\ThumbnailSize;

class Config
{
    public static function getCloudflareAccountHash(): mixed
    {
        return config('salvon.media.cloudflare_account_hash');
    }

    public static function getCloudflareAccountID(): mixed
    {
        return config('salvon.media.cloudflare_account_id');
    }

    public static function getCloudflareApiToken(): mixed
    {
        return config('salvon.media.cloudflare_api_token');
    }

    public static function checkIsCloudflareCredentialsNull(): bool
    {
        if (self::getCloudflareAccountID() === null) {
            return true;
        }

        if (self::getCloudflareAccountHash() === null) {
            return true;
        }

        return self::getCloudflareApiToken() === null;
    }

    public static function getAvailableVariants(): array
    {
        return config('salvon.media.available_variants', [
            'thumbnail' => ThumbnailSize::SM,
            'small' => ThumbnailSize::MD,
            'medium' => ThumbnailSize::XL,
        ]);
    }

    public static function getThumbSizes(): array
    {
        return config('salvon.media.thumb_sizes', Defaults::thumbSizes());
    }

    public static function getThumbQuality(): mixed
    {
        return config('salvon.media.quality', Defaults::quality());
    }
}
