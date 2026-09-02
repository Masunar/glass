<?php

declare(strict_types=1);

namespace Salvon\Regon\Parser;

use Exception;
use Salvon\Regon\DTO\FullReport;
use Salvon\Regon\Definition\Report;
use Salvon\Regon\Parser\Trait\HandleResult;

final class FullReportParser
{
    use HandleResult;

    /**
     * @throws Exception
     */
    public static function execute(Report $report, string $data): ?FullReport
    {
        $xml = self::handleResult($data);

        if(is_null($xml)) {
            return null;
        }

        $data = $xml->dane;

        if(!Report::isArrayable($report)) {
            return new FullReport($data);
        }

        $results = [];
        foreach ($data as $value) {
            $results[] = $value;
        }

        return new FullReport($results);
    }
}
