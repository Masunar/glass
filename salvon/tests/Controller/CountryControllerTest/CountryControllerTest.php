<?php

declare(strict_types=1);

namespace Salvon\Tests\Controller\CountryControllerTest;

use Throwable;
use Salvon\Tests\ControllerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

class CountryControllerTest extends ControllerTestCase
{
    /**
     * @throws Throwable
     */
    public function testGetCountries(): void
    {
        $response = $this->get('/api/country');

        $response->assertStatus(Response::HTTP_OK);

        $this->assertResponseItems(__FUNCTION__, $response);
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('getCountryDataProvider')]
    public function testGetCountry(string $iso): void
    {
        $response = $this->get('/api/country/' . $iso);

        $response->assertStatus(Response::HTTP_OK);

        $responseContent = (string) $response->getContent();
        $this->assertDataToResource(__FUNCTION__, $responseContent, sprintf('%s.json', $iso));
    }

    public static function getCountryDataProvider(): array
    {
        return [['iso' => 'pl'], ['iso' => 'us']];
    }
}
