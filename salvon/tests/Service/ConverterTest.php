<?php

declare(strict_types=1);

namespace Salvon\Tests\Service;

use Salvon\Tests\TestCase;
use Salvon\Service\Converter;

class ConverterTest extends TestCase
{
    public function testArrayItemsToInt(): void
    {
        $pre = ['123', 432, null, '0987', '0000', 0];

        $this->assertEquals(
            [123, 432, 0, 987, 0, 0],
            Converter::arrayItemsToInt($pre),
        );
    }
}
