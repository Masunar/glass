<?php

declare(strict_types=1);

namespace Salvon\Enum;

enum SubPermission: string
{
    case WILDCARD = '*';
    case LIST = 'list';
    case CREATE = 'create';
    case READ = 'read';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case RESTORE = 'restore';
}
