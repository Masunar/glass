<?php

declare(strict_types=1);

namespace App\Validators\Auth;

use Salvon\Validator\Validator;
use App\DTO\Auth\AuthenticationDTO;

final class AuthenticationValidator extends Validator
{
    public function violations(AuthenticationDTO $input): bool
    {
        $this->violation(
            'email',
            $input,
            'required',
        );

        $this->violation(
            'password',
            $input,
            'required',
        );

        return $this->validated();
    }
}
