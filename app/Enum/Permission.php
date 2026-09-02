<?php

declare(strict_types=1);

namespace App\Enum;

enum Permission: string
{
    case USERS = 'users';
    case ROLES = 'roles';
    case PERMISSIONS = 'permissions';

    case LOCATIONS = 'locations';
    case STATUSES = 'statuses';
    case ALERTS = 'alerts';
    case AUDIT = 'audit';
}
