<?php

namespace Salvon\Bundle\Media\Model;

use Override;
use Salvon\Model\Dateable;
use Salvon\Bundle\Media\Enum\MediaType;
use Salvon\Bundle\Media\Enum\MediaProvider;

/**
 * @property string $uuid
 * @property int|MediaProvider $provider
 * @property MediaType $media_type
 * @property string $location
 * @property string $name
 * @property string $mime_type
 * @property string $extension
 * @property array $metadata
 * @property string $checksum
 */
class Media extends Dateable
{
    protected $fillable = [
        'uuid',
        'provider',
        'media_type',
        'location',
        'name',
        'mime_type',
        'extension',
        'metadata',
        'checksum',
    ];

    protected $hidden = [
        'metadata',
        'location',
    ];

    #[Override]
    protected function casts(): array
    {
        return [
            'provider' => MediaProvider::class,
            'media_type' => MediaType::class,
            'metadata' => 'array',
        ];
    }
}
