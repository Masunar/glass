<?php

namespace Salvon\Validator;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ValidationException extends BadRequestHttpException
{
    public function __construct(
        public readonly array $validationErrors,
        string $message = '',
        ?\Throwable $previous = null,
        int $code = 0,
        array $headers = [],
    ) {
        parent::__construct($message, $previous, $code, $headers);
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}
