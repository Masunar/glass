<?php

declare(strict_types=1);

namespace Salvon\Regon\Client;

use Exception;
use SoapFault;
use Salvon\Regon\Data\Collection;
use Salvon\Regon\DTO\PkdCode;
use Salvon\Regon\DTO\SearchResult;
use Salvon\Regon\Parser\PkdParser;
use Salvon\Regon\Definition\Report;
use Salvon\Regon\DTO\CompanyDetails;
use Salvon\Regon\Definition\LegalForm;
use Salvon\Regon\Definition\SearchType;
use Salvon\Regon\Parser\CompanyReportParser;
use Salvon\Regon\Parser\CompanyDetailsParser;
use Salvon\Regon\DTO\CompanyReport\CompanyReport;
use Salvon\Regon\Definition\ReportSearchIdentifierType;

final readonly class FullReportClient extends RegonClient
{
    /**
     * @throws SoapFault
     * @throws Exception
     * @return Collection<PkdCode>|null
     */
    public function pkd(string $identifier, ReportSearchIdentifierType $identifierType = ReportSearchIdentifierType::NIP): ?Collection
    {
        $searchResult = $this->searchForReport($identifier, $identifierType);

        if(is_null($searchResult)) {
            return null;
        }

        $legalForm = LegalForm::fromSearchResult($searchResult);

        return $this->pkdReport($searchResult, $legalForm);
    }

    /**
     * @throws SoapFault
     * @throws Exception
     */
    public function company(string $identifier, ReportSearchIdentifierType $identifierType = ReportSearchIdentifierType::NIP): ?CompanyReport
    {
        $searchResult = $this->searchForReport($identifier, $identifierType);

        if(is_null($searchResult)) {
            return null;
        }

        $legalForm = LegalForm::fromSearchResult($searchResult);

        return $this->companyReport($searchResult, $legalForm);
    }

    /**
     * @throws SoapFault
     * @throws Exception
     */
    public function details(string $identifier, ReportSearchIdentifierType $identifierType = ReportSearchIdentifierType::NIP): ?CompanyDetails
    {
        $searchResult = $this->searchForReport($identifier, $identifierType);

        if(is_null($searchResult)) {
            return null;
        }

        $legalForm = LegalForm::fromSearchResult($searchResult);

        /** @var Collection<PkdCode> $pkdCodes */
        $pkdCodes = $this->pkdReport($searchResult, $legalForm);
        /** @var CompanyReport $companyReport */
        $companyReport = $this->companyReport($searchResult, $legalForm);

        return CompanyDetailsParser::execute(
            $searchResult,
            $legalForm,
            $pkdCodes,
            $companyReport,
        );
    }

    /**
     * @throws SoapFault
     * @throws Exception
     */
    protected function searchForReport(string $identifier, ReportSearchIdentifierType $identifierType = ReportSearchIdentifierType::NIP): ?SearchResult
    {
        $searchType = SearchType::from($identifierType->value);
        $this->validateInputData($searchType, [$identifier]);

        $searchResult = $this->searchBy($searchType, $identifier)?->first();

        if(is_null($searchResult)) {
            return null;
        }

        return $searchResult;
    }

    /**
     * @throws SoapFault
     * @throws Exception
     * @return Collection<PkdCode>|null
     */
    protected function pkdReport(SearchResult $searchResult, LegalForm $legalForm): ?Collection
    {
        $reportType = Report::pkdReportFromLegalForm($legalForm);

        if(is_null($searchResult->regon)){
            return null;
        }

        $fullReportResult = $this->fullReport($reportType, $searchResult->regon);

        if(is_null($fullReportResult)){
            return null;
        }

        return PkdParser::execute($fullReportResult);
    }

    /**
     * @throws SoapFault
     * @throws Exception
     */
    protected function companyReport(SearchResult $searchResult, LegalForm $legalForm): ?CompanyReport
    {
        $reportTypes = Report::companyReportFromLegalForm($legalForm);

        if(is_null($searchResult->regon)){
            return null;
        }

        $reports = [];
        foreach ($reportTypes as $reportType) {
            /** @var array<string, string|null>|null $report */
            $report = $this->fullReport($reportType, $searchResult->regon)?->toArray();

            if(!is_null($report)) {
                $reports[] = $report;
            }
        }

        if($reports === []) {
            return null;
        }

        return CompanyReportParser::execute($legalForm, $reports);
    }
}
