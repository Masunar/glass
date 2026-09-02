<?php

namespace Salvon\BDO\DTO;

final readonly class EupSearchResults
{
    public function __construct(
        /** @var array<EupSearchResult> */
        public array   $results = [],
        public int     $totalResultNumber = 0,
        public int     $pageNumber = 0,
        public int     $totalPages = 0,
        public int     $pageSize = 0,
        public bool    $hasPreviousPage = false,
        public bool    $hasNextPage = false,
        public bool    $applicationCreatedByOfficial = false,
        public string  $applicationId = '',
        public ?string $applicationType = null,
        public ?string $applicationTypeCodename = null,
    )
    {}
}
