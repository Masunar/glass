<?php

declare(strict_types=1);

namespace Salvon\Regon\Parser;

use Exception;
use Salvon\Regon\Data\Collection;
use Salvon\Regon\DTO\SearchResult;
use Salvon\Regon\Parser\Trait\HandleResult;
use Salvon\Regon\XML\ArrayableSimpleXMLElement;

final class SearchParser
{
    use HandleResult;

    /**
     * @throws Exception
     * @return Collection<SearchResult>|null
     */
    public static function execute(string $data): ?Collection
    {
        $xml = self::handleResult($data);

        if(is_null($xml)) {
            return null;
        }

        $data = $xml->dane;

        $results = [];
        foreach ($data as $value) {
            $results[] = self::createDataObject($value);
        }

        return new Collection($results);
    }

    private static function createDataObject(ArrayableSimpleXMLElement $data): SearchResult
    {
        return new SearchResult(
            regon: (string) $data->Regon,
            nip: (string) $data->Nip,
            nipStatus: (string) $data->StatusNip,
            name: (string) $data->Nazwa,
            province: (string) $data->Wojewodztwo,
            district: (string) $data->Powiat,
            commune: (string) $data->Gmina,
            city: (string) $data->Miejscowosc,
            postcode: (string) $data->KodPocztowy,
            postcodeCity: (string) $data->MiejscowoscPoczty,
            street: (string) $data->Ulica,
            propertyNumber: (string) $data->NrNieruchomosci,
            apartmentNumber: (string) $data->NrLokalu,
            type: (string) $data->Typ,
            silosId: (string) $data->SilosID,
            activityEndedAt: (string) $data->DataZakonczeniaDzialalnosci,
        );
    }
}
