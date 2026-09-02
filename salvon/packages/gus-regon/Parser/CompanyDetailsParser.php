<?php

declare(strict_types=1);

namespace Salvon\Regon\Parser;

use Exception;
use Salvon\Regon\Data\Collection;
use Salvon\Regon\DTO\PkdCode;
use Salvon\Regon\DTO\SearchResult;
use Salvon\Regon\DTO\CompanyDetails;
use Salvon\Regon\Definition\LegalForm;
use Salvon\Regon\DTO\CompanyReport\CompanyReport;

final class CompanyDetailsParser
{
    /**
     * @throws Exception
     * @param Collection<PkdCode> $pkdCodes
     */
    public static function execute(
        SearchResult $searchResult,
        LegalForm $legalForm,
        Collection $pkdCodes,
        ?CompanyReport $companyReport,
    ): CompanyDetails {
        return new CompanyDetails(
            searchResult: $searchResult,
            legalForm: $legalForm,
            pkd: $pkdCodes->toArray(),
            report: self::companyReport($companyReport, $searchResult),
        );
    }

    private static function companyReport(?CompanyReport $report, SearchResult $searchResult): CompanyReport
    {
        if ($report instanceof CompanyReport) {
            return $report;
        }

        return new CompanyReport(
            regon9: $searchResult->regon,
            nip: $searchResult->nip,
        );
    }
}
