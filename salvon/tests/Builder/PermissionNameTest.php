<?php

declare(strict_types=1);

namespace Salvon\Tests\Builder;

use Salvon\Tests\TestCase;
use Salvon\Enum\Permission;
use Salvon\Enum\SubPermission;
use PHPUnit\Framework\Attributes\DataProvider;

class PermissionNameTest extends TestCase
{
    #[DataProvider('dataProvider')]
    public function testBuild(array $list, string $expected): void
    {
        $actual = permission(...$list);

        $this->assertEquals($expected, $actual);
    }

    public static function dataProvider(): array
    {
        return [
            [
                'list' => ['admin_access'],
                'expected' => 'admin_access',
            ],
            [
                'list' => ['users', '*'],
                'expected' => 'users.*',
            ],
            [
                'list' => ['admin', 'users', '*'],
                'expected' => 'admin.users.*',
            ],
            [
                'list' => [Permission::SUPERUSER],
                'expected' => 'superuser',
            ],
            [
                'list' => ['users', SubPermission::LIST],
                'expected' => 'users.list',
            ],
        ];
    }
}
