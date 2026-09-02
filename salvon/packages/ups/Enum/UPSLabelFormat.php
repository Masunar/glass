<?php

declare(strict_types=1);

namespace Salvon\UPS\Enum;

enum UPSLabelFormat: string
{
    case GIF = 'GIF';
    case PDF = 'PDF';
    case ZPL = 'ZPL';
    case EPL = 'EPL';
    case SPL = 'SPL';
}
