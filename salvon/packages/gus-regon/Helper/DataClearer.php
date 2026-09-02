<?php

namespace Salvon\Regon\Helper;

final readonly class DataClearer
{
    public static function nip(string $nip): string
    {
        $nip = str_replace('-', '', $nip);
        $nip = str_replace(' ', '', $nip);
        return trim($nip);
    }

    /**
     * @param string[] $nips
     * @return string[]
     */
    public static function nips(array $nips): array
    {
        $clearedNips = [];
        foreach ($nips as $nip) {
            $clearedNips[] = self::nip($nip);
        }

        return $clearedNips;
    }
}
