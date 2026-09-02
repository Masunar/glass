<?php

declare(strict_types=1);

namespace Salvon\UPS\Enum;

enum UPSTrackingStatus: string
{
    case Manifest = 'M';
    case InTransit = 'I';
    case Pickup = 'P';
    case OutForDelivery = 'O';
    case Delivered = 'D';
    case Exception = 'X';
    case ReturnedToShipper = 'RS';
    case Unknown = 'U';

    public static function fromCode(?string $code): self
    {
        return self::tryFrom((string) $code) ?? self::Unknown;
    }
}
