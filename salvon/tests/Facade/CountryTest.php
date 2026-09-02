<?php

declare(strict_types=1);

namespace Salvon\Tests\Facade;

use Salvon\Facade\Country;
use Salvon\Tests\TestCase;
use Rinvex\Country\Country as RinvexCountry;
use PHPUnit\Framework\Attributes\DataProvider;

class CountryTest extends TestCase
{
    public function testFromIso(): void
    {
        $country = Country::fromIso('us');

        $this->assertNotNull($country);
        $this->assertInstanceOf(RinvexCountry::class, $country);
        $this->assertEquals('United States', $country->getName());

        $country = Country::fromIso('test');
        $this->assertNull($country);
    }

    #[DataProvider('dataProvider')]
    public function testAsArray(string $iso, array $expected): void
    {
        $country = Country::fromIso($iso);
        $this->assertNotNull($country);

        $array = Country::asArray($country);
        $this->assertEquals($expected, $array);
    }

    #[DataProvider('dataProvider')]
    public function testArrayFromIso(string $iso, array $expected): void
    {
        $array = Country::arrayFromIso($iso);
        $this->assertEquals($expected, $array);
    }

    public static function dataProvider(): array
    {
        return [
            [
                'iso' => 'us',
                'expected' => [
                    'name' => 'United States',
                    'official_name' => 'United States of America',
                    'native_official_name' => 'United States of America',
                    'iso_3166_1_alpha2' => 'US',
                    'iso_3166_1_alpha3' => 'USA',
                    'calling_code' => '1',
                    'currency' => 'USD',
                    'emoji' => '🇺🇸',
                    'display_name' => 'United States 🇺🇸',
                ],
            ],
        ];
    }
}
