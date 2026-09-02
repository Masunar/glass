<?php

declare(strict_types=1);

namespace Salvon\Imagicker\Enum;

enum ThumbnailSize: string
{
    case XS = 'xs';
    case SM = 'sm';
    case MD = 'md';
    case LG = 'lg';
    case XL = 'xl';
    case XXL = '2xl';
    case XXXL = '3xl';
    case XXXXL = '4xl';
}
