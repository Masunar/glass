<?php

declare(strict_types=1);

namespace Salvon\Regon\DTO;

use Salvon\Regon\XML\ArrayableSimpleXMLElement;

final readonly class FullReport
{
    public function __construct(
        /**
         * @var array<int, ArrayableSimpleXMLElement>|ArrayableSimpleXMLElement $data
         */
        public array|ArrayableSimpleXMLElement $data,
    ) {}

    /** @return array<int|string, mixed> */
    public function toArray(): array
    {
        return is_array($this->data) ? $this->data : $this->data->toArray();
    }
}
