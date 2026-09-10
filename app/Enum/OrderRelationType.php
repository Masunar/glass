<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Za co odpowiada zlecenie podrzędne.
 *
 * W starym systemie relacja żyła w treści notatki („reklamacyjne
 * (główne zlecenie) > zlecenie zerowe#23686”), więc koszt własny
 * reklamacji nigdzie się nie sumował.
 */
enum OrderRelationType: string
{
    case CLAIM = 'claim';
    case COMPLETION = 'completion';
    case REWORK = 'rework';
}
