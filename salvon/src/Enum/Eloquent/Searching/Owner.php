<?php

declare(strict_types=1);

namespace Salvon\Enum\Eloquent\Searching;

enum Owner: string
{
    case OWN = 'own';
    case RELATION = 'relation';
}
