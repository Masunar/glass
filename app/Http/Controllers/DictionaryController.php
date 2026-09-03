<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\Permission;
use Illuminate\Http\Request;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;
use App\Services\DictionaryService;

/**
 * Słowniki proste — jeden kontroler na wszystkie zakładki.
 *
 * Uprawnienie jest wspólne dla całego ekranu; węższe uprawnienia
 * poszczególnych słowników (np. sekcje cenowe) pilnuje dodatkowo
 * własny ekran, na który te dane trafiają.
 */
class DictionaryController extends ApiController
{
    public function __construct(
        private readonly DictionaryService $service,
    ) {
        $this->protect(['schema', 'rows'], Permission::DICTIONARIES->value, SubPermission::LIST->value);
        $this->protect(['create'], Permission::DICTIONARIES->value, SubPermission::CREATE->value);
        $this->protect(['update'], Permission::DICTIONARIES->value, SubPermission::UPDATE->value);
        $this->protect(['deactivate'], Permission::DICTIONARIES->value, SubPermission::UPDATE->value);
    }

    public function schema(): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse([
            'dictionaries' => $this->service->describe(),
        ]));
    }

    public function rows(Request $request, string $slug): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse([
            'rows' => $this->service->rows($slug, $request->boolean('include_inactive')),
        ]));
    }

    public function create(Request $request, string $slug): JsonResponse
    {
        return $this->secure(function () use ($request, $slug): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->service->save($slug, $input);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse(['id' => $result['id']]);
        });
    }

    public function update(Request $request, string $slug, int $id): JsonResponse
    {
        return $this->secure(function () use ($request, $slug, $id): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->service->save($slug, $input, $id);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->updatedResponse();
        });
    }

    public function deactivate(string $slug, int $id): JsonResponse
    {
        return $this->secure(function () use ($slug, $id): JsonResponse {
            $this->service->deactivate($slug, $id);

            return $this->updatedResponse();
        });
    }
}
