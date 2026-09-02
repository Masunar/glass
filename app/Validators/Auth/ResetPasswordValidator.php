<?php

declare(strict_types=1);

namespace App\Validators\Auth;

use Illuminate\Validation\Rule;
use Salvon\Validator\Validator;
use App\DTO\Auth\ResetPasswordDTO;

final class ResetPasswordValidator extends Validator
{
    protected function violations(ResetPasswordDTO $input): void
    {
        $this->violation(
            'email',
            $input,
            'email|required',
        );

        $this->violation(
            'password',
            $input,
            ['password' => ['required', Rule::in($input->passwordConfirmation)]],
            ['password.in' => 'validation.passwords_mismatch'],
        );
    }
}
