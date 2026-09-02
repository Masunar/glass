<?php

declare(strict_types=1);

namespace App\Enum;

enum MfaType: string
{
    case TOTP = 'totp';
    case EMAIL = 'email';
    //case SMS = 'sms';
}
