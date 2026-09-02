<?php

namespace Salvon\Regon\Definition;

enum BulkReport: string
{
    case NEW_COMPANIES = 'BIR11NowePodmiotyPrawneOrazDzialalnosciOsFizycznych';
    case UPDATED_COMPANIES = 'BIR11AktualizowanePodmiotyPrawneOrazDzialalnosciOsFizycznych';
    case DELETED_COMPANIES = 'BIR11SkreslonePodmiotyPrawneOrazDzialalnosciOsFizycznych';

    case NEW_LOCAL_UNITS = 'BIR11NoweJednostkiLokalne';
    case UPDATED_LOCAL_UNITS = 'BIR11AktualizowaneJednostkiLokalne';
    case DELETED_LOCAL_UNITS = 'BIR11SkresloneJednostkiLokalne';
}
