<?php

namespace Salvon\Regon;

final readonly class Url
{
    public static function wsdl(bool $useTestEnv = false): string
    {
        if ($useTestEnv) {
            return self::testWSDL();
        }

        return self::productionWSDL();
    }

    public static function serverLocation(bool $useTestEnv = false): string
    {
        if ($useTestEnv) {
            return self::testServerLocation();
        }

        return self::productionServerLocation();
    }

    public static function testWSDL(): string
    {
        return 'https://wyszukiwarkaregontest.stat.gov.pl/wsBIR/wsdl/UslugaBIRzewnPubl-ver11-test.wsdl';
    }

    public static function testServerLocation(): string
    {
        return 'https://wyszukiwarkaregontest.stat.gov.pl/wsBIR/UslugaBIRzewnPubl.svc';
    }

    public static function productionWSDL(): string
    {
        return 'https://wyszukiwarkaregon.stat.gov.pl/wsBIR/wsdl/UslugaBIRzewnPubl-ver11-prod.wsdl';
    }

    public static function productionServerLocation(): string
    {
        return 'https://wyszukiwarkaregon.stat.gov.pl/wsBIR/UslugaBIRzewnPubl.svc';
    }

    public static function action(string $action): string
    {
        return sprintf('http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/%s', $action);
    }

    public static function headerAddressingNamespace(): string
    {
        return 'http://www.w3.org/2005/08/addressing';
    }
}
