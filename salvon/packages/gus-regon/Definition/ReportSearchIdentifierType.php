<?php

declare(strict_types=1);

namespace Salvon\Regon\Definition;

enum ReportSearchIdentifierType: string
{
    case NIP = 'Nip';
    case KRS = 'Krs';
    case REGON = 'Regon';
}
