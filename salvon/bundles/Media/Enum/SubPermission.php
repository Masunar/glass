<?php

namespace Salvon\Bundle\Media\Enum;

use Salvon\Contract\Enum\PermissionBackedEnum;

enum SubPermission: string implements PermissionBackedEnum
{
    public const array EXCLUDED_FROM_SEEDER = [
        self::UPLOAD,
        self::DELETE,
        self::SET_DEFAULT,
        self::SET_POSITIONS,
    ];

    case UPLOAD = 'upload';
    case DELETE = 'delete';
    case SET_DEFAULT = 'set_default';
    case SET_POSITIONS = 'set_positions';
}
