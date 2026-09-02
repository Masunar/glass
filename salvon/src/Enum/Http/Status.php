<?php

declare(strict_types=1);

namespace Salvon\Enum\Http;

enum Status: string
{
    case SUCCESS = 'success';
    case OK = 'ok';
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
    case VALIDATION_ERROR = 'validation_error';
    case BAD_REQUEST = 'bad_request';
    case UNAUTHORIZED = 'unauthorized';
    case ISE = 'internal_server_error';
    case FORBIDDEN = 'forbidden';
    case ACCESS_DENIED = 'access_denied';
    case NOT_FOUND = 'not_found';
    case ERROR = 'error';
    case METHOD_NOT_ALLOWED = 'method_not_allowed';
    case NOT_ACCEPTABLE = 'not_acceptable';
    case THROTTLE = 'throttle';
    case MFA_REQUIRED = 'mfa_required';
    case MFA_INVALID = 'mfa_invalid';
}
