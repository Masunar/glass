<?php

declare(strict_types=1);

namespace Salvon\Regon\Parser;

use SimpleXMLElement;
use Salvon\Regon\DTO\PkdCode;
use Salvon\Regon\DTO\FullReport;
use Salvon\Regon\Data\Collection;
use Salvon\Regon\Exception\InvalidPkdDataArgument;

final class PkdParser
{
    /**
     * @throws InvalidPkdDataArgument
     * @return Collection<PkdCode>
     */
    public static function execute(?FullReport $fullReport): Collection
    {
        if (is_null($fullReport)) {
            return new Collection([]);
        }

        if (!is_array($fullReport->data)) {
            throw new InvalidPkdDataArgument('Full Report data must be array of pkd codes.');
        }

        $results = [];
        foreach ($fullReport->data as $pkd) {
            $results[] = self::createDataObject($pkd);
        }

        return new Collection($results);
    }

    /**
     * @throws InvalidPkdDataArgument
     */
    private static function createDataObject(SimpleXMLElement $data): PkdCode
    {
        $data = (array) $data;

        $pkdKeyNeedles = [
            '_pkdWersja' => 'version',
            '_pkdKod' => 'code',
            '_pkdNazwa' => 'name',
            '_pkdPrzewazajace' => 'prevalent',
        ];

        $parsedData = [];
        foreach (array_keys($data) as $key) {
            $matchedNeedle = self::matchPkdKey($pkdKeyNeedles, $key);

            if (!is_null($matchedNeedle)) {
                $parsedData[$matchedNeedle] = $data[$key];
            }
        }

        if ($parsedData === []) {
            throw new InvalidPkdDataArgument('Full Report data must be array of pkd codes.');
        }

        return new PkdCode(
            version: (string) $parsedData['version'],
            code: (string) $parsedData['code'],
            name: (string) $parsedData['name'],
            prevalent: (bool) intval($parsedData['prevalent'] ?? 0),
        );
    }

    /** @param array<string, string> $pkdKeyNeedles */
    private static function matchPkdKey(array $pkdKeyNeedles, string $key): ?string
    {
        foreach ($pkdKeyNeedles as $needle => $argumentName) {
            if (str_ends_with($key, $needle)) {
                return $argumentName;
            }
        }

        return null;
    }
}
