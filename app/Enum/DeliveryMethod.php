<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Sposób wydania zlecenia.
 *
 * Stary system kodował to jednym polem `O1|O2|M|D`, mieszając sposób
 * wydania z punktem odbioru. Punkt ma sens wyłącznie przy odbiorze
 * własnym — przy montażu i dowozie jedziemy do klienta, więc miejsce
 * wydania nie jest wtedy wymiarem.
 */
enum DeliveryMethod: string
{
    case PICKUP = 'pickup';
    case INSTALLATION = 'installation';
    case DELIVERY = 'delivery';
}
