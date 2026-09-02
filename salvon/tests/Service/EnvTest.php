<?php

declare(strict_types=1);

namespace Salvon\Tests\Service;

use Salvon\Service\Env;
use Salvon\Tests\TestCase;

class EnvTest extends TestCase
{
    public function testIsDev(): void
    {
        $this->assertFalse(Env::isDev());
    }

    public function testIsProduction(): void
    {
        $this->assertFalse(Env::isProduction());
    }
}
