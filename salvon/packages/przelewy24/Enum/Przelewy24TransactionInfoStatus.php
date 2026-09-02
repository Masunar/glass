<?php

declare(strict_types=1);

namespace Salvon\Przelewy24\Enum;

enum Przelewy24TransactionInfoStatus: int
{
    case NO_PAYMENT = 0;
    case ADVANCE_PAYMENT = 1;
    case PAYMENT_MADE = 2;
    case PAYMENT_RETURNED = 3;
}
