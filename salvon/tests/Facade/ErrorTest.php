<?php

declare(strict_types=1);

namespace Salvon\Tests\Facade;

use Exception;
use Throwable;
use TypeError;
use Salvon\Facade\Error;
use Salvon\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ErrorTest extends TestCase
{
    public function testThrow(): void
    {
        $this->assertThrows(static fn() => Error::throw(message: 'Test exception'), Exception::class);
    }

    /**
     * @param class-string<Throwable> $instance
     */
    #[DataProvider('dataProvider')]
    public function testThrownInstances(string $method, string $instance): void
    {
        $this->assertThrows(static fn() => Error::$method(), $instance);
    }

    public static function dataProvider(): array
    {
        return [
            [
                'method' => 'ise',
                'instance' => Exception::class,
            ],
            [
                'method' => 'notFound',
                'instance' => NotFoundHttpException::class,
            ],
            [
                'method' => 'badRequest',
                'instance' => BadRequestHttpException::class,
            ],
            [
                'method' => 'accessDenied',
                'instance' => AccessDeniedHttpException::class,
            ],
            [
                'method' => 'unauthorized',
                'instance' => AccessDeniedHttpException::class,
            ],
            [
                'method' => 'type',
                'instance' => TypeError::class,
            ],
        ];
    }
}
