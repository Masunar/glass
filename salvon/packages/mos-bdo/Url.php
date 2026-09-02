<?php

namespace Salvon\BDO;

final readonly class Url
{
    public static function search(): string
    {
        return 'https://rejestr-bdo.mos.gov.pl/Dictionary/Application/Search';
    }

    public static function wasteCodes(string $phrase): string
    {
        return sprintf('https://rejestr-bdo.mos.gov.pl/Dictionary/WasteCode/Search/%s', $phrase);
    }

    public static function wasteProcess(string $phrase): string
    {
        return sprintf('https://rejestr-bdo.mos.gov.pl/Dictionary/WasteCode/Search/%s', $phrase);
    }

    public static function eupSearch(): string
    {
        return 'https://rejestr-bdo.mos.gov.pl/Application/Preview/GetEups';
    }
}
