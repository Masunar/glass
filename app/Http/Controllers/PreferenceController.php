<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;
use App\Services\UserPreferences;

/**
 * Własne ustawienia ekranu.
 *
 * Bez `protect()`: każdy zalogowany zmienia wyłącznie swoje ustawienia,
 * a lista dozwolonych kluczy i wartości stoi w `UserPreferences`.
 */
class PreferenceController extends ApiController
{
    public function __construct(
        private readonly UserPreferences $preferences,
    ) {
    }

    public function update(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            $user = $request->user();

            if (!$user instanceof User) {
                return $this->unauthorizedResponse();
            }

            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->preferences->save($user, $input);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->updatedResponse();
        });
    }
}
