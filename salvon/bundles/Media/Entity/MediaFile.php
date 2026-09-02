<?php

namespace Salvon\Bundle\Media\Entity;

use Carbon\Carbon;
use DateTimeInterface;
use Salvon\Bundle\Media\Enum\MediaType;
use Salvon\Bundle\Media\Enum\MediaProvider;

class MediaFile
{
    public ?int $id;

    public ?string $uuid;

    public MediaProvider $provider;

    public MediaType $mediaType;

    public ?string $location;

    public ?string $name;

    public ?string $mimeType;

    public ?array $metadata;

    public ?array $variants;

    public ?string $url;

    public string|DateTimeInterface $uploaded;

    /**
     * @param MediaProvider $provider
     * @param MediaType $mediaType
     * @param string|DateTimeInterface|null $uploaded
     * @param string|null $location
     * @param string|null $name
     * @param string|null $mimeType
     * @param array|null $metadata
     * @param array|null $variants
     * @param string|null $uuid
     * @param string|null $url
     * @param ?int $id
     */
    public function __construct(
        MediaProvider                 $provider,
        MediaType                     $mediaType,
        string|DateTimeInterface|null $uploaded = null,
        ?string                       $location = null,
        ?string                       $name = null,
        ?string                       $mimeType = null,
        ?array                        $metadata = null,
        ?array                        $variants = null,
        ?string                       $uuid = null,
        ?int                          $id = null,
        ?string                       $url = null,
    ) {
        $this->id = $id;
        $this->uuid = $uuid;
        $this->provider = $provider;
        $this->mediaType = $mediaType;
        $this->location = $location;
        $this->name = $name;
        $this->mimeType = $mimeType;
        $this->metadata = $metadata;
        $this->variants = $variants;
        $this->url = $url;
        $this->uploaded = Carbon::parse($uploaded);
    }
}
