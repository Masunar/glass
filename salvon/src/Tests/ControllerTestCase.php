<?php

declare(strict_types=1);

namespace Salvon\Tests;

use Override;
use Carbon\Carbon;
use Illuminate\Testing\TestResponse;

abstract class ControllerTestCase extends DatabaseTestCase
{
    protected $defaultHeaders = [
        'accept' => 'application/json',
        'content-type' => 'application/json',
    ];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2024-01-01 12:00:00'));
    }

    public function assertResponseItems(string $functionName, TestResponse $response, string $resource = 'expected.json', string $itemsKey = 'items', string $resourceDir = 'Resources'): void
    {
        $actual = $response->json('data')[$itemsKey];
        $this->assertDataToResource($functionName, $actual, $resource, $resourceDir);
    }
}
