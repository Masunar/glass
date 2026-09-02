<?php

declare(strict_types=1);

namespace Salvon\Validator;

use Salvon\Service\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Salvon\Contract\DataTransferObject;
use Illuminate\Support\Facades\Validator as LaravelValidator;

class Validator
{
    private array $errors = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function validated(): bool
    {
        return empty($this->getErrors());
    }

    public static function handle(Request|array $request, array $rules, array $messages = []): array
    {
        $validator = new self();
        return $validator->validate($request, $rules, $messages);
    }

    public static function throwable(Request|array $request, array $rules, array $messages = []): void
    {
        $errors = self::handle($request, $rules, $messages);

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    protected function clearErrors(): void
    {
        $this->errors = [];
    }

    protected function violation(string $field, Request|DataTransferObject|array $input, string|array $constraints, array $messages = []): void
    {
        $value = null;

        if (instance_of($input, DataTransferObject::class)) {
            $cases = Str::programmingCases($field, false);

            foreach ($cases as $case) {
                if (property_exists($input, $case)) {
                    $value = $input->$case;
                    break;
                }
            }
        }

        if (instance_of($input, Request::class)) {
            $value = $input->post($field);
        }

        $this->buildViolation($field, $value, $constraints, $messages);
    }

    protected function buildViolation(string $field, mixed $value, string|array $constraints, array $messages = []): void
    {
        $errors = LaravelValidator::make(
            [$field => $value],
            [$field => $constraints],
            $messages !== [] ? [$field => $messages[$field]] : [],
        )->errors()->toArray();

        if (!empty($errors)) {
            $this->addErrors($field, $errors);
        }
    }

    protected function buildArrayViolation(string $responseField, array $value, array $constraints, array $messages = []): void
    {
        $errors = LaravelValidator::make(
            $value,
            $constraints,
            $messages,
        )->errors()->toArray();

        if (!empty($errors)) {
            $this->addErrors($responseField, $errors);
        }
    }

    protected function addErrors(string $field, array $messages): void
    {
        foreach ($messages as $message) {
            $this->errors[$field][] = $message;
        }
    }

    private function validate(Request|DataTransferObject|array $input, array $rules, array $messages = []): array
    {
        $this->clearErrors();

        // Keep current locale
        $locale = App::getLocale();
        // Set locale to next for build in validation translations bypass
        App::setLocale('react_router');

        foreach (array_keys($rules) as $key) {
            $this->violation($key, $input, $rules[$key], $messages);
        }

        // Restore current locale
        App::setLocale($locale);

        return $this->getErrors();
    }
}
