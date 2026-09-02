<?php

declare(strict_types=1);

namespace App\Enum;

enum Permission: string
{
    case USERS = 'users';
    case ROLES = 'roles';
    case PERMISSIONS = 'permissions';
}
