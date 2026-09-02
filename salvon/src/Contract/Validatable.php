<?php

declare(strict_types=1);

namespace Salvon\Contract;

interface Validatable
{
    public function isValid(DataTransferObject $data, array $options = []): bool;

    public function getErrors(): array;
}
