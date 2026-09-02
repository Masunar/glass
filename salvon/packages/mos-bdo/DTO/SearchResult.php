<?php

namespace Salvon\BDO\DTO;

final readonly class SearchResult
{
    public function __construct(
        public ?string $uuid = null,
        public ?string $name = null,
        public ?string $bdoNumber = null,
        public ?string $nip = null,
        public ?string $nipEup = null,
    )
    {}
}
