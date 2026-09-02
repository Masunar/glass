<?php

declare(strict_types=1);

namespace Salvon\Contract;

interface BootablePackage
{
    public function boot(): void;
}
