<?php

declare(strict_types=1);

namespace Salvon\Tests\Collection;

use stdClass;
use Salvon\Tests\TestCase;
use InvalidArgumentException;
use Salvon\Collection\TArray;

class TArrayTest extends TestCase
{
    public function testPrimitiveType(): void
    {
        $instance = new TArray('int');

        $this->assertEquals('int', $instance->getType());

        $instance->add(1);
        $instance->add(2);
        $instance->add(3);

        $this->assertCount(3, $instance->toArray());

        $this->assertThrows(static fn(): bool => $instance->add('test'), InvalidArgumentException::class);
    }

    public function testComplexType(): void
    {
        $instance = new TArray(stdClass::class);

        $this->assertEquals(stdClass::class, $instance->getType());

        $instance->add(new stdClass());
        $instance->add(new stdClass());

        $this->assertCount(2, $instance->toArray());

        $this->assertThrows(static fn(): bool => $instance->add(new TArray('int')), InvalidArgumentException::class);
    }
}
