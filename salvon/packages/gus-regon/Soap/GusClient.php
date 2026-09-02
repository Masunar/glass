<?php

declare(strict_types=1);

namespace Salvon\Regon\Soap;

use SoapFault;
use SoapHeader;
use stdClass;
use Salvon\Regon\Url;
use Salvon\Regon\Definition\Method;

use const SOAP_1_2;
use const SOAP_DOCUMENT;

final readonly class GusClient
{
    /**
     * @throws SoapFault
     * @param array<string, mixed> $args
     * @return stdClass
     */
    public static function call(Method $method, array $args, ?string $sessionId = null, bool $useTestEnv = false): mixed
    {
        $client = self::client($sessionId, $useTestEnv);
        $location = Url::serverLocation($useTestEnv);
        $client->__setLocation($location);

        /** @var stdClass */
        return $client->__soapCall($method->value, [$args], null, [
            self::header('Action', Url::action($method->value)),
            self::header('To', $location),
        ]);
    }

    /**
     * @throws SoapFault
     */
    private static function client(?string $sessionId = null, bool $useTestEnv = false): SoapClient
    {
        $wsdl = Url::wsdl($useTestEnv);

        $options = [
            'soap_version' => SOAP_1_2,
            'trace' => true,
            'style' => SOAP_DOCUMENT,
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => sprintf('sid: %s', $sessionId),
                ],
            ]),
        ];

        return new SoapClient($wsdl, $options);
    }

    private static function header(string $name, string $value): SoapHeader
    {
        return new SoapHeader(
            Url::headerAddressingNamespace(),
            $name,
            $value,
        );
    }
}
