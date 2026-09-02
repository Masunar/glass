<?php

declare(strict_types=1);

namespace Salvon\Tests;

use Salvon\Service\Instance;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function getResourcePath(string $functionName, string $resource = 'expected.json', string $resourceDir = 'Resources'): string
    {
        $reflection = reflection($this);

        $directory = dirname((string) $reflection->getFileName());

        return sprintf('%s/%s/%s/%s', $directory, $resourceDir, $functionName, $resource);
    }

    public function getResourceContent(string $functionName, string $resource = 'expected.json', string $resourceDir = 'Resources'): string
    {
        return (string) file_get_contents($this->getResourcePath($functionName, $resource, $resourceDir));
    }

    /**
     * @param string|array<float|int|string, mixed> $actual
     */
    public function assertDataToResource(string $functionName, string|array $actual, string $resource = 'expected.json', string $resourceDir = 'Resources'): void
    {
        $expected = $this->getResourcePath($functionName, $resource, $resourceDir);

        if (!is_string($actual)) {
            $actual = json_encode($actual);
        }

        $this->assertJsonStringEqualsJsonFile($expected, (string) $actual);
    }
}
