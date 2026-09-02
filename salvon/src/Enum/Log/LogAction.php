<?php

declare(strict_types=1);

namespace Salvon\Enum\Log;

enum LogAction: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case RESTORE = 'restore';
    case AUTHENTICATION = 'authentication';
}
