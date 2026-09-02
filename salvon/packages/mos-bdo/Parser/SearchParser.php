<?php

namespace Salvon\BDO\Parser;

use Salvon\BDO\DTO\SearchResult;
use Salvon\BDO\DTO\SearchResults;

final readonly class SearchParser
{
    public static function execute(string $content): ?SearchResults
    {
        $data = json_decode($content, true);

        if(!$data){
            return null;
        }

        return self::createDataObject($data);
    }

    private static function createDataObject(array $data): SearchResults
    {
        $items = $data['items'] ?? [];

        return new SearchResults(
            results: self::parseItems($items),
            wasteCodeName: $data['wasteCodeName'] ?? '',
            wasteProcessName: $data['wasteProcessName'] ?? '',
            totalResultNumber: (int)($data['totalResultNumber'] ?? 0),
            pageNumber: (int)($data['pageNumber'] ?? 0),
            pageSize: (int)($data['pageSize'] ?? 0),
            hasPreviousPage: (bool)($data['hasPreviousPage'] ?? false),
            hasNextPage: (bool)($data['hasNextPage'] ?? false),
        );
    }

    private static function parseItems(array $items): array
    {
        return array_map(function (array $item){
            return new SearchResult(
                uuid: $item['application_company_id'],
                name: $item['name'],
                bdoNumber: $item['registration_number'],
                nip: $item['nip'],
                nipEup: $item['nip_eup'],
            );
        }, $items);
    }
}
