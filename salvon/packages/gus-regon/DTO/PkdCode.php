<?php

declare(strict_types=1);

namespace Salvon\Regon\DTO;

use Override;
use Salvon\Regon\Helper\Converter;

final class PkdCode
{
    public ?string $symbol = null;

    public function __construct(
        public readonly ?string $version = null,
        public readonly ?string $code = null,
        public readonly ?string $name = null,
        public readonly ?bool $prevalent = null,
    ) {
        $this->symbol = Converter::pkdCodeToSymbol($code);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->code . ' ' . $this->name;
    }
}
