<?php

namespace Salvon\BDO\Parser;

use Salvon\BDO\DTO\EupSearchResult;
use Salvon\BDO\DTO\EupSearchResults;
use Salvon\BDO\DTO\SearchResult;

final readonly class EupSearchParser
{
    public static function execute(string $content): ?EupSearchResults
    {
        $data = json_decode($content, true);

        if(!$data){
            return null;
        }

        return self::createDataObject($data);
    }

    private static function createDataObject(array $data): EupSearchResults
    {
        $items = $data['items'] ?? [];

        return new EupSearchResults(
            results: self::parseItems($items),
            totalResultNumber: (int)($data['totalResultNumber'] ?? 0),
            pageNumber: (int)($data['pageNumber'] ?? 0),
            pageSize: (int)($data['pageSize'] ?? 0),
            hasPreviousPage: (bool)($data['hasPreviousPage'] ?? false),
            hasNextPage: (bool)($data['hasNextPage'] ?? false),
            applicationCreatedByOfficial: (bool)($data['applicationCreatedByOfficial'] ?? false),
            applicationId: $data['applicationId'] ?? '',
            applicationType: $data['applicationType'] ?? null,
            applicationTypeCodename: $data['applicationTypeCodename'] ?? null,
        );
    }

    private static function parseItems(array $items): array
    {
        return array_map(function (array $item){
            return new EupSearchResult(
                addressHtml: $item['addressHtml'] ?? null,
                name: $item['name'],
                eupId: $item['eupId'] ?? null,
                isHeadquarters: (bool)$item['isHeadquarters'] ?? false,
                isActive: (bool)$item['isActive'] ?? false,
                canBeDeleted: (bool)$item['canBeDeleted'] ?? false,
            );
        }, $items);
    }
}
