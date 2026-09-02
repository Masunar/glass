<?php

declare(strict_types=1);

namespace App\Validators\Auth;

use Salvon\Validator\Validator;
use App\DTO\Auth\AuthenticationDTO;

final class ResetPasswordRequestValidator extends Validator
{
    protected function violations(AuthenticationDTO $input): void
    {
        $this->violation(
            'email',
            $input,
            'email|required',
        );
    }
}
