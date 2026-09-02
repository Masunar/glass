<?php

namespace Salvon\BDO\DTO;

final readonly class SearchData
{
    public function __construct(
        public string $queryString = '',
        public int $pageNumber = 1,
        public string $placeType = 'residenceOrBusinessAddress',
        public string $companyProvince = '',
        public string $companyDistrict = '',
        public string $companyCommune = '',
        public array  $wasteProcesses = [],
        public array  $wasteCodes = [],
        public array  $tables = [],
    ) {}

    public function toArray(): array
    {
        return [
            'queryString' => $this->queryString,
            'pageNumber' => $this->pageNumber,
            'placeType' => $this->placeType,
            'companyProvince' => $this->companyProvince,
            'companyDistrict' => $this->companyDistrict,
            'companyCommune' => $this->companyCommune,
            'wasteProcesses' => $this->wasteProcesses,
            'wasteCodes' => $this->wasteCodes,
            'tables' => $this->tables,
        ];
    }
}
