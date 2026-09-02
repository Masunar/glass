<?php

declare(strict_types=1);

namespace Salvon\Regon\Definition;

enum ErrorCode: int
{
    case NOT_FOUND = 4;
    case DEREGISTERED_BEFORE_2014 = 11;
}
