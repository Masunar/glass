<?php

declare(strict_types=1);

namespace Salvon\Enum;

enum DateTimeFormat: string
{
    public const DateTimeFormat DEFAULT = self::EU_DEV_CLASSIC;

    case PL_CLASSIC = 'd-m-Y H:i:s';
    case EU_DEV_CLASSIC = 'Y-m-d H:i:s';
    case US = 'm/d/Y h:i:s A';
    case EU_FOR_FILE = 'Y-m-d_H:i:s';
    case US_FOR_FILE = 'm-d-Y_h:i:s_A';
}
