<?php

declare(strict_types=1);

namespace Salvon\Regon\Definition;

enum SearchType: string
{
    public const array NIP_CASES = [
        self::NIP,self::NIPS,
    ];

    public const array REGON_CASES = [
        self::REGON,self::REGONS_9, self::REGONS_14,
    ];

    case NIP = 'Nip';
    case KRS = 'Krs';
    case REGON = 'Regon';
    case NIPS = 'Nipy';
    case KRSES = 'Krsy';
    case REGONS_9 = 'Regony9zn';
    case REGONS_14 = 'Regony14zn';
}
