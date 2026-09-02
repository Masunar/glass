<?php

declare(strict_types=1);

namespace App\Validators\Auth;

use Salvon\Validator\Validator;
use App\DTO\Auth\ChangePasswordDTO;

final class ChangePasswordValidator extends Validator
{
    public function violations(ChangePasswordDTO $input): bool
    {
        $this->violation('current_password', $input, 'required');
        $this->violation('password', $input, 'required|min:8');
        $this->violation('confirm_password', $input, 'required');

        return $this->validated();
    }
}
