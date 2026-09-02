<?php

declare(strict_types=1);

namespace Salvon\Regon\Definition;

enum Param: string
{
    case USER_KEY = 'pKluczUzytkownika';
    case SESSION_ID = 'pIdentyfikatorSesji';
    case SEARCH = 'pParametryWyszukiwania';
    case REGON = 'pRegon';
    case REPORT_NAME = 'pNazwaRaportu';
    case REPORT_DATE = 'pDataRaportu';
}
