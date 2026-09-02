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
    case PARAMETERS = 'parameters';
    case PRICE_LIST = 'price_list';
    case PRODUCTS = 'products';
    case CONTRACTORS = 'contractors';
}
