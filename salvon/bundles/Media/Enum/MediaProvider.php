<?php

declare(strict_types=1);

namespace Salvon\Bundle\Media\Enum;

enum MediaProvider: int
{
    case LOCAL = 0;
    case BASE64 = 1;
    case CLOUDFLARE = 3;
}
