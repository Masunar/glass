<?php

namespace Salvon\Validator\Rule;

use Closure;
use Override;
use Illuminate\Contracts\Validation\ValidationRule;

final class Ids implements ValidationRule
{
    public static function rule(): self
    {
        return new Ids();
    }

    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            $fail('validation.must_be_array');
        }

        if (empty($value)) {
            $fail('validation.required');
        }

        foreach ($value as $id) {
            $this->validateId($id, $fail);
        }
    }

    private function validateId(mixed $id, Closure $fail): void
    {
        if (!is_numeric($id)) {
            $fail('validation.must_be_valid_id');
        }

        $id = (int) $id;

        if ($id < 1) {
            $fail('validation.must_be_valid_id');
        }
    }
}
