<?php

declare(strict_types=1);

namespace Salvon\Enum\Eloquent\Searching;

enum Concatenation: string
{
    case OR = 'or';
    case AND = 'and';
}
