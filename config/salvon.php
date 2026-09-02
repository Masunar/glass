<?php

use Salvon\Enum\Locale;
use Salvon\Bundle\Media\Media;
use Salvon\Enum\DateTimeFormat;
use Salvon\Imagicker\Enum\ThumbnailSize;

return [
    'framework' => [
        'locale_enum' => Locale::class,
        'default_locale' => Locale::PL,
        'default_cast_date_format' => DateTimeFormat::DEFAULT->value,
        'discovery_bundles' => false,
        'bundles' => [
            Media::class,
        ],
    ],
    'regon' => [
        'token' => env('REGON_API_KEY', 'abcde12345abcde12345'),
        'use_test_env' => env('REGON_USE_TEST_ENV', true),
    ],
    'mfa' => [
        'app_name' => env('MFA_APP_NAME', 'Salvon SSR'),
    ],
    'media' => [
        'cloudflare_account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'cloudflare_api_token' => env('CLOUDFLARE_API_TOKEN'),
        'cloudflare_account_hash' => env('CLOUDFLARE_ACCOUNT_HASH'),
        'available_variants' => [
            'thumbnail' => ThumbnailSize::SM,
            'small' => ThumbnailSize::LG,
            'public' => ThumbnailSize::XXXL,
            'bg' => ThumbnailSize::XXXXL,
        ],
        'thumb_sizes' => [
            ThumbnailSize::XS->value => 50,
            ThumbnailSize::SM->value => 100,
            ThumbnailSize::MD->value => 200,
            ThumbnailSize::LG->value => 300,
            ThumbnailSize::XL->value => 400,
            ThumbnailSize::XXL->value => 500,
            ThumbnailSize::XXXL->value => 800,
            ThumbnailSize::XXXXL->value => 1200,
        ],
        'quality' => 90,
    ],
];
