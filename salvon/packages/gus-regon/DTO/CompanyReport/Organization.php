<?php

declare(strict_types=1);

namespace Salvon\Regon\DTO\CompanyReport;

final readonly class Organization
{
    public function __construct(
        public ?string $foundingAuthoritySymbol = null, //organZalozycielski_Symbol
        public ?string $foundingAuthorityName = null, //organZalozycielski_Nazwa
    ) {}
}
