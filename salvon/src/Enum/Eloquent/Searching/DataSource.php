<?php

declare(strict_types=1);

namespace Salvon\Enum\Eloquent\Searching;

enum DataSource: string
{
    case SEARCH = 'search';
    case OTHER = 'other';
}
