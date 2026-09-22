<?php

declare(strict_types=1);

namespace App\Enum;

enum EmailTemplateCode: string
{
    case RESET_PASSWORD = 'reset_password';
    case PASSWORD_CHANGED = 'password_changed';
    case USER_MFA = 'user_mfa';
    case USER_MFA_SETUP = 'user_mfa_setup';

    /**
     * Oferta do klienta. Szablon zyje tu, a nie w parametrach
     * wyceny: miejsce na tresc maila juz istnieje i ma swoj ekran,
     * a druga lista szablonow obok tej rozjechalaby sie z nia przy
     * pierwszej poprawce.
     */
    case OFFER = 'offer';
}
