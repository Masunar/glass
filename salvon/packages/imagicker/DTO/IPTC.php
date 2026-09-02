<?php

declare(strict_types=1);

namespace Salvon\Imagicker\DTO;

final readonly class IPTC
{
    public function __construct(
        public ?string $author = null,
        public ?string $title = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $country = null,
        public ?string $headline = null,
        public ?string $copyright = null,
        public ?string $description = null,
        public ?string $specialInstructions = null,
    ) {}
}
