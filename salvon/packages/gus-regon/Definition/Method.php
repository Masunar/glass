<?php

declare(strict_types=1);

namespace Salvon\Regon\Definition;

enum Method: string
{
    case LOGIN = 'Zaloguj';
    case LOGOUT = 'Wyloguj';
    case SEARCH = 'DaneSzukajPodmioty';
    case FULL_REPORT = 'DanePobierzPelnyRaport';
    case BULK_REPORT = 'DanePobierzRaportZbiorczy';
}
