<?php

namespace Salvon\Enum;

enum AuthState: string
{
    case AUTHENTICATED = 'authenticated';
    case UNAUTHENTICATED = 'unauthenticated';
    case INACTIVE = 'inactive';
    case MFA_REQUIRED = 'mfa_required';
    case MFA_INVALID = 'mfa_invalid';
}
