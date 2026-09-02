<?php

namespace Salvon\BDO\DTO;

final readonly class EupSearchResult
{
    public function __construct(
        public ?string $addressHtml = null,
        public ?string $name = null,
        public ?string $eupId = null,
        public ?bool $isHeadquarters = null,
        public ?bool $isActive = null,
        public ?bool $canBeDeleted = null,
    )
    {}
}
