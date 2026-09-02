<?php

declare(strict_types=1);

namespace Salvon\Controller;

use Salvon\Facade\Country;
use Illuminate\Http\JsonResponse;
use Rinvex\Country\CountryLoaderException;

class CountryController extends ApiController
{
    public function countries(): JsonResponse
    {
        return $this->secure(function (): JsonResponse {
            return $this->dataResponse(
                ['items' => array_map(static fn(array $country): array => [
                    ...$country,
                    'display_name' => $country['native_name'] . ' ' . $country['emoji'],
                ], array_values(countries()))],
            );
        });
    }

    public function country(string $iso): JsonResponse
    {
        return $this->secure(function () use ($iso): JsonResponse {
            try {
                $country = country($iso);
            } catch (CountryLoaderException) {
                $this->notFound();
            }

            return $this->dataResponse(Country::asArray($country));
        });
    }
}
