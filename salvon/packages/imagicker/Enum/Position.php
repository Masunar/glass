<?php

declare(strict_types=1);

namespace Salvon\Imagicker\Enum;

enum Position: string
{
    case TOP_LEFT = 'top-left';
    case TOP_CENTER = 'top-center';
    case TOP_RIGHT = 'top-right';
    case MIDDLE_LEFT = 'middle-left';
    case MIDDLE_CENTER = 'middle-center';
    case MIDDLE_RIGHT = 'middle-right';
    case BOTTOM_LEFT = 'bottom-left';
    case BOTTOM_CENTER = 'bottom-center';
    case BOTTOM_RIGHT = 'bottom-right';
}
