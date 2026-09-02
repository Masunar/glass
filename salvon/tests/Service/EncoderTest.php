<?php

declare(strict_types=1);

namespace Salvon\Tests\Service;

use Salvon\Tests\TestCase;
use Salvon\Service\Encoder;

class EncoderTest extends TestCase
{
    public function testBase64(): void
    {
        $string = 'test base64 encode';

        $encoded = Encoder::base64($string);

        $this->assertEquals('dGVzdCBiYXNlNjQgZW5jb2Rl', $encoded);

        $decoded = Encoder::fromBase64($encoded);

        $this->assertEquals($string, $decoded);
    }

    public function testBase64Utf8(): void
    {
        $string = 'test base64 encode ąśćżźóę';

        $encoded = Encoder::toBase64Utf8($string);

        $this->assertEquals('dGVzdCBiYXNlNjQgZW5jb2RlIMSFxZvEh8W8xbrDs8SZ', $encoded);

        $decoded = Encoder::fromBase64($encoded);

        $this->assertEquals($string, $decoded);
    }

    public function testJson(): void
    {
        $arr = ['test' => '123', 'second' => 321];

        $encoded = Encoder::json($arr);

        $this->assertEquals('{"test":"123","second":321}', $encoded);

        $decoded = Encoder::arrayFromJson((string) $encoded);

        $this->assertEquals($arr, $decoded);
    }
}
