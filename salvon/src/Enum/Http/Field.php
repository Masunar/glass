<?php

declare(strict_types=1);

namespace Salvon\Enum\Http;

enum Field: string
{
    case FIELD = 'field';
    case STATUS = 'status';
    case MESSAGE = 'message';
    case DATA = 'data';
    case TRACE = 'trace';
    case ERRORS = 'errors';
    case EXCEPTION = 'exception';
    case FILE = 'file';
    case LINE = 'line';
}
