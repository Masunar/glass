<?php

namespace Salvon\BDO;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Salvon\Api\HasGuzzleClient;
use Salvon\BDO\DTO\EupSearchResults;
use Salvon\BDO\DTO\SearchData;
use Salvon\BDO\DTO\SearchResults;
use Salvon\BDO\DTO\WasteCodes;
use Salvon\BDO\DTO\WasteProcesses;
use Salvon\BDO\Parser\EupSearchParser;
use Salvon\BDO\Parser\SearchParser;
use Salvon\BDO\Parser\WasteCodesParser;
use Salvon\BDO\Parser\WasteProcessesParser;

final readonly class BDOClient
{
    use HasGuzzleClient;

    /**
     * @throws GuzzleException
     */
    public function search(SearchData $searchData): ?SearchResults
    {
        $client = self::guzzleClient();

        $content = $client->post(Url::search(), [
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::JSON => $searchData->toArray(),
            RequestOptions::HEADERS => [
                'Content-Type' => 'application/json',
            ],
        ])->getBody()->getContents();

        return SearchParser::execute($content);
    }

    /**
     * @throws GuzzleException
     */
    public function wasteCodes(string $phrase): ?WasteCodes
    {
        $content = $this->get(Url::wasteCodes($phrase));

        return WasteCodesParser::execute($content);
    }

    /**
     * @throws GuzzleException
     */
    public function wasteProcesses(string $phrase): ?WasteProcesses
    {
        $content = $this->get(Url::wasteProcess($phrase));

        return WasteProcessesParser::execute($content);
    }

    /**
     * @throws GuzzleException
     */
    private function get(string $url): string
    {
        $client = self::guzzleClient();

        return $client->get($url, [
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::HEADERS => [
                'Content-Type' => 'application/json',
            ],
        ])->getBody()->getContents();
    }

    /**
     * @throws GuzzleException
     */
    public function eupSearch(string $uuid): ?EupSearchResults
    {
        $client = self::guzzleClient();

        $content = $client->post(Url::eupSearch(), [
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::JSON => ['applicationId' => $uuid],
            RequestOptions::HEADERS => [
                'Content-Type' => 'application/json',
            ],
        ])->getBody()->getContents();

        return EupSearchParser::execute($content);
    }
}
