<?php

declare(strict_types=1);

namespace Salvon\Bundle\Media\Enum;

enum MediaType: int
{
    case IMAGE = 0;
    case PDF = 1;
    case VIDEO = 2;
    case AUDIO = 3;
    case DOCUMENT = 4;
    case SPREADSHEET = 5;
    case ARCHIVE = 6;
    case SVG_ICON = 7;
}
