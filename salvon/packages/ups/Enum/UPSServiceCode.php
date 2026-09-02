<?php

declare(strict_types=1);

namespace Salvon\UPS\Enum;

enum UPSServiceCode: string
{
    case Standard = '11';
    case ExpressSaver = '65';
    case Express = '07';
    case ExpeditedExpress = '54';
    case ExpressPlus = '14';
    case WorldwideEconomyDDU = '17';
    case WorldwideEconomyDDP = '72';
    case AccessPointEconomy = '70';
}
