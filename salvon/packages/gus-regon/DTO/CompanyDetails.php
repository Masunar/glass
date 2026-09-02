<?php

declare(strict_types=1);

namespace Salvon\Regon\DTO;

use Salvon\Regon\Definition\LegalForm;
use Salvon\Regon\DTO\CompanyReport\CompanyReport;

final readonly class CompanyDetails
{
    public function __construct(
        public SearchResult  $searchResult,
        public LegalForm     $legalForm,
        /** @var array<PkdCode> */
        public array         $pkd,
        public CompanyReport $report,
    ) {}
}
