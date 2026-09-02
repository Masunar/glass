<?php

declare(strict_types=1);

namespace Salvon\Regon\Parser\Trait;

use Exception;
use Salvon\Regon\Definition\ErrorCode;
use Salvon\Regon\Exception\RegonError;
use Salvon\Regon\XML\ArrayableSimpleXMLElement;

trait HandleResult
{
    /**
     * @throws Exception
     */
    public static function handleResult(string $data): ?ArrayableSimpleXMLElement
    {
        $xml = new ArrayableSimpleXMLElement($data);
        $data = $xml->dane;
        $error = $data?->ErrorCode;

        if ((string) $error === (string) ErrorCode::NOT_FOUND->value) {
            return null;
        }

        if ((string) $error === (string) ErrorCode::DEREGISTERED_BEFORE_2014->value) {
            return null;
        }

        if ((string) $error !== '' && (string) $error !== '0') {
            throw new RegonError((string) $data->ErrorMessageEn);
        }

        return $xml;
    }
}
