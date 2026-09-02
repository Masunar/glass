<?php

namespace Salvon\Regon\XML;

use SimpleXMLElement;
use Salvon\Regon\Helper\Converter;

class ArrayableSimpleXMLElement extends SimpleXMLElement
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return Converter::simpleXmlToArray($this) ?? [];
    }
}
