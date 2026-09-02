<?php

declare(strict_types=1);

namespace Salvon\Regon\Parser;

use Exception;
use Salvon\Regon\Data\Collection;
use Salvon\Regon\Parser\Trait\HandleResult;

final class BulkReportParser
{
    use HandleResult;

    /**
     * @throws Exception
     * @return Collection<string>|null
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
            $results[] = (string) $value->regon;
        }

        return new Collection($results);
    }
}
