<?php

declare(strict_types=1);

namespace Salvon\UPS\Enum;

enum UPSPackagingType: string
{
    case Letter = '01';
    case CustomerSuppliedPackage = '02';
    case Tube = '03';
    case Pak = '04';
    case ExpressBox = '21';
    case Box25Kg = '24';
    case Box10Kg = '25';
    case Pallet = '30';
    case SmallExpressBox = '2a';
    case MediumExpressBox = '2b';
    case LargeExpressBox = '2c';
}
