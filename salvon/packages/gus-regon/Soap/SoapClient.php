<?php

declare(strict_types=1);

namespace Salvon\Regon\Soap;

use Override;

final class SoapClient extends \SoapClient
{
    #[Override]
    public function __doRequest(string $request, string $location, string $action, int $version, bool $oneWay = false, ?string $uriParserClass = null): string
    {
        $response = parent::__doRequest($request, $location, $action, $version, $oneWay) ?? '';

        return self::decode($response);
    }

    public static function decode(string $response): string
    {
        $response = (string) stristr($response, '<s:');
        $response = (string) stristr($response,'</s:Envelope>',true);

        return sprintf("%s</s:Envelope>", $response);
    }
}
