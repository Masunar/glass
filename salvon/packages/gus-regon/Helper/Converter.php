<?php

namespace Salvon\Regon\Helper;

use SimpleXMLElement;

final readonly class Converter
{
    /** @return null|array<string, mixed> */
    public static function simpleXmlToArray(SimpleXMLElement $xmlElement): ?array
    {
        $output = array_map(static function (mixed $node) {
            return ($node instanceof SimpleXMLElement)
                ? self::simpleXmlToArray($node)
                : $node;
        }, (array) $xmlElement);

        return $output === [] ? null : $output;
    }

    public static function pkdCodeToSymbol(?string $pkdCode): ?string
    {
        if (is_null($pkdCode) || !preg_match('/^(\d{2})(\d{2})([A-Z])$/', $pkdCode, $matches)) {
            return null;
        }

        return sprintf('PKD %s.%s.%s', $matches[1], $matches[2], $matches[3]);
    }
}
