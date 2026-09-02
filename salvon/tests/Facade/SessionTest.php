<?php

declare(strict_types=1);

namespace Salvon\Tests\Facade;

use Salvon\Facade\Session;
use Salvon\Tests\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;

class SessionTest extends TestCase
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testStoreGetForget(): void
    {
        Session::store('test', 'value');

        $this->assertEquals('value', Session::get('test'));

        Session::forget('test');

        $this->assertEquals(null, Session::get('test'));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testDestroy(): void
    {
        Session::store('test', 'value');

        $this->assertEquals('value', Session::get('test'));

        Session::destroy();

        $this->assertEquals(null, Session::get('test'));
    }
}
