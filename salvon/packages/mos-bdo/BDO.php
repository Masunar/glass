<?php

namespace Salvon\BDO;

use GuzzleHttp\Exception\GuzzleException;
use Salvon\BDO\DTO\EupSearchResults;
use Salvon\BDO\DTO\SearchData;
use Salvon\BDO\DTO\SearchResult;
use Salvon\BDO\DTO\SearchResults;
use Salvon\BDO\DTO\WasteCodes;
use Salvon\BDO\DTO\WasteProcesses;

class BDO
{
    public static function client(): BDOClient
    {
        return new BDOClient();
    }

    /**
     * @throws GuzzleException
     */
    public static function search(SearchData $searchData): ?SearchResults
    {
        return self::client()->search($searchData);
    }

    /**
     * @throws GuzzleException
     */
    public static function wasteCodes(string $phrase = 'a'): ?WasteCodes
    {
        return self::client()->wasteCodes($phrase);
    }

    /**
     * @throws GuzzleException
     */
    public static function wasteProcesses(string $phrase = 'a'): ?WasteProcesses
    {
        return self::client()->wasteProcesses($phrase);
    }

    public static function byNip(string $nip): ?SearchResult
    {
        try {
            $data = self::client()->search(new SearchData($nip));
            foreach ($data->results as $result){
                if($result->nip === $nip){
                    return $result;
                }
            }

            return null;
        } catch (GuzzleException){
            return null;
        }
    }

    public static function eupByNip(string $nip): ?EupSearchResults
    {
        $data = self::byNip($nip);
        if ($data === null) {
            return null;
        }
        try {
            $eupData = self::client()->eupSearch($data->uuid);
        } catch (GuzzleException $e) {
            return null;
        }
        if ($eupData === null) {
            return null;
        }
        return $eupData;
    }
}
