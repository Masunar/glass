<?php

declare(strict_types=1);

namespace Salvon\Google\ReCaptcha;

use Salvon\Api\HasGuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

final readonly class ReCaptcha
{
    use HasGuzzleClient;

    /**
     * @throws GuzzleException
     * @throws ReCaptchaException
     */
    public static function validate(string $value): void
    {
        $data = [
            'secret' => getenv('GOOGLE_RECAPTCHA_SECRET'),
            'response' => $value,
        ];

        $queryParams = http_build_query($data, '', '&');
        $url = 'https://www.google.com/recaptcha/api/siteverify?' . $queryParams;

        $client = self::guzzleClient();
        $response = $client->post($url);

        $responseContent = json_decode($response->getBody()->getContents());

        if (!$responseContent->success) {
            throw new ReCaptchaException();
        }
    }
}
