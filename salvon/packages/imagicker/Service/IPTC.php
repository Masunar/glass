<?php

namespace Salvon\Imagicker\Service;

use Salvon\Imagicker\DTO\IPTC as ParsedIPTC;

final class IPTC
{
    public static function parse(string $path): ParsedIPTC
    {
        getimagesize($path, $info);
        $iptcData = iptcparse($info['APP13'] ?? '');

        return new ParsedIPTC(
            author: $iptcData['2#080'][0] ?? null,
            title: $iptcData['2#005'][0] ?? null,
            city: $iptcData['2#090'][0] ?? null,
            state: $iptcData['2#095'][0] ?? null,
            country: $iptcData['2#101'][0] ?? null,
            headline: $iptcData['2#105'][0] ?? null,
            copyright: $iptcData['2#116'][0] ?? null,
            description: $iptcData['2#120'][0] ?? null,
            specialInstructions: $iptcData['2#040'][0] ?? null,
        );
    }
}
