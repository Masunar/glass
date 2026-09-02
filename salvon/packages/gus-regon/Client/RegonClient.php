<?php

declare(strict_types=1);

namespace Salvon\Regon\Client;

use DateTimeInterface;
use Exception;
use SoapFault;
use Salvon\Regon\Data\Collection;
use Salvon\Regon\Definition\BulkReport;
use Salvon\Regon\Definition\Method;
use Salvon\Regon\Definition\Param;
use Salvon\Regon\Definition\Report;
use Salvon\Regon\Definition\SearchType;
use Salvon\Regon\DTO\FullReport;
use Salvon\Regon\DTO\SearchResult;
use Salvon\Regon\Exception\UnauthorizedException;
use Salvon\Regon\Parser\BulkReportParser;
use Salvon\Regon\Parser\FullReportParser;
use Salvon\Regon\Parser\SearchParser;
use Salvon\Regon\Soap\GusClient;
use Salvon\Regon\Validator\Nip;
use Salvon\Regon\Validator\Regon;

readonly class RegonClient
{
    protected ?string $sessionId;

    /**
     * @throws SoapFault
     */
    public function __construct(protected string $apiKey, protected bool $useTestEnv = false)
    {
        $response = GusClient::call(
            Method::LOGIN,
            [
                Param::USER_KEY->value => $apiKey,
            ],
            null,
            $useTestEnv,
        );

        $sessionId = $response->ZalogujResult ?? null;

        if(empty($sessionId)){
            throw new UnauthorizedException();
        }

        $this->sessionId = $sessionId;
    }

    /**
     * @throws SoapFault
     * @throws Exception
     * @param string|string[] $data
     * @return Collection<SearchResult>|null
     */
    protected function searchBy(SearchType $type, string|array $data): ?Collection
    {
        if(is_string($data)) {
            $data = [$data];
        }

        $this->validateInputData($type, $data);

        $response = GusClient::call(Method::SEARCH, [
            Param::SEARCH->value => [
                $type->value => implode(',', $data),
            ],
        ], $this->sessionId, $this->useTestEnv);

        return SearchParser::execute((string) $response->DaneSzukajPodmiotyResult);
    }

    /**
     * @throws SoapFault
     * @throws Exception
     */
    protected function fullReport(Report $report, string $regon): ?FullReport
    {
        $this->validateInputData(SearchType::REGON, [$regon]);

        $response = GusClient::call(Method::FULL_REPORT, [
            Param::REGON->value => $regon,
            Param::REPORT_NAME->value => $report->value,
        ], $this->sessionId, $this->useTestEnv);

        return FullReportParser::execute($report, (string) $response->DanePobierzPelnyRaportResult);
    }

    /**
     * @throws SoapFault
     * @throws Exception
     * @return Collection<string>|null
     */
    protected function bulkReport(BulkReport $bulkReport, DateTimeInterface $date): ?Collection
    {
        $response = GusClient::call(Method::BULK_REPORT, [
            Param::REPORT_DATE->value => $date->format("Y-m-d"),
            Param::REPORT_NAME->value => $bulkReport->value,
        ], $this->sessionId, $this->useTestEnv);

        return BulkReportParser::execute((string) $response->DanePobierzRaportZbiorczyResult);
    }

    /**
     * @param string[] $data
     */
    protected function validateInputData(SearchType $type, array $data): void
    {
        if(in_array($type, SearchType::NIP_CASES)) {
            Nip::validate($data);
        }

        if(in_array($type, SearchType::REGON_CASES)) {
            Regon::validate($data);
        }
    }

    /**
     * @throws SoapFault
     */
    public function __destruct()
    {
        GusClient::call(Method::LOGOUT, [
            Param::SESSION_ID->value => $this->sessionId,
        ]);
    }
}
