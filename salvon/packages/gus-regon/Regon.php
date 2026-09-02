<?php

declare(strict_types=1);

namespace Salvon\Regon;

use SoapFault;
use Salvon\Regon\Client\SearchClient;
use Salvon\Regon\Client\BulkReportClient;
use Salvon\Regon\Client\FullReportClient;

final readonly class Regon
{
    public function __construct(
        private string $token,
        private bool $useTestEnv,
    ) {}

    /**
     * @throws SoapFault
     */
    public function search(): SearchClient
    {
        return self::searchClient($this->token, $this->useTestEnv);
    }

    /**
     * @throws SoapFault
     */
    public function report(): FullReportClient
    {
        return self::reportClient($this->token, $this->useTestEnv);
    }

    /**
     * @throws SoapFault
     */
    public function bulkReport(): BulkReportClient
    {
        return self::bulkReportClient($this->token, $this->useTestEnv);
    }

    /**
     * @throws SoapFault
     */
    public static function searchClient(string $token, bool $useTestEnv): SearchClient
    {
        return new SearchClient($token, $useTestEnv);
    }

    /**
     * @throws SoapFault
     */
    public static function reportClient(string $token, bool $useTestEnv): FullReportClient
    {
        return new FullReportClient($token, $useTestEnv);
    }

    /**
     * @throws SoapFault
     */
    public static function bulkReportClient(string $token, bool $useTestEnv): BulkReportClient
    {
        return new BulkReportClient($token, $useTestEnv);
    }
}
