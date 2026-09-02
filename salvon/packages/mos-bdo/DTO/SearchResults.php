<?php

namespace Salvon\BDO\DTO;

final readonly class SearchResults
{
    public function __construct(
        /** @var array<SearchResult> */
        public array $results = [],
        public ?string $wasteCodeName = null,
        public ?string $wasteProcessName = null,
        public int $totalResultNumber = 0,
        public int $pageNumber = 0,
        public int $totalPages = 0,
        public int $pageSize = 0,
        public bool $hasPreviousPage = false,
        public bool $hasNextPage = false,
    )
    {}
}
