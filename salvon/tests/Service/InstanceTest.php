<?php

declare(strict_types=1);

namespace Salvon\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionException;
use Salvon\Contract\DataTransferObject;
use Salvon\DTO\DTO;
use Salvon\DTO\Request\QueryParamDTO;
use Salvon\Service\Instance;
use Salvon\Tests\Service\Resources\DTO\TestDTO;
use Salvon\Tests\TestCase;
use stdClass;

class InstanceTest extends TestCase
{
    #[DataProvider('dataProvider')]
    public function testOf(mixed $item, string $of, bool $expected): void
    {
        $this->assertEquals($expected, Instance::of($item, $of));
    }

    /**
     * @throws ReflectionException
     */
    public static function dataProvider(): array
    {
        return [
            [
                'item' => new stdClass(),
                'of' => stdClass::class,
                'expected' => true,
            ],
            [
                'item' => (static function (): stdClass {
                    $class = new stdClass();
                    $class->name = 'test';
                    return $class;
                })(),
                'of' => stdClass::class,
                'expected' => true,
            ],
            [
                'item' => new QueryParamDTO(0, 10, 'id', 'desc', ''),
                'of' => DataTransferObject::class,
                'expected' => true,
            ],
            [
                'item' => new TestDTO('test', 0),
                'of' => DTO::class,
                'expected' => true,
            ],
            [
                'item' => new TestDTO('test', 0),
                'of' => DataTransferObject::class,
                'expected' => true,
            ],
            [
                'item' => TestDTO::class,
                'of' => DataTransferObject::class,
                'expected' => true,
            ],
            [
                'item' => TestDTO::class,
                'of' => DTO::class,
                'expected' => true,
            ],
            [
                'item' => TestDTO::class,
                'of' => DTO::class,
                'expected' => true,
            ],
            [
                'item' => TestDTO::class,
                'of' => DataTransferObject::class,
                'expected' => true,
            ],
            [
                'item' => Instance::reflection(stdClass::class),
                'of' => ReflectionClass::class,
                'expected' => true,
            ],
            [
                'item' => new QueryParamDTO(0, 10, 'id', 'desc', ''),
                'of' => DTO::class,
                'expected' => false,
            ],
            [
                'item' => Instance::reflection(Instance::class),
                'of' => Instance::class,
                'expected' => false,
            ],
            [
                'item' => new stdClass(),
                'of' => 'int',
                'expected' => false,
            ],
            [
                'item' => new stdClass(),
                'of' => 'object',
                'expected' => false,
            ],
        ];
    }
}
