<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\Permission;
use Illuminate\Http\Request;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;
use App\Services\GlobalParameterService;

/**
 * Parametry wzoru wyceny i teksty ofertowe — odpowiednik zakładki
 * „Ogólne" w słownikach starego systemu.
 */
class GlobalParameterController extends ApiController
{
    public function __construct(
        private readonly GlobalParameterService $service,
    ) {
        $this->protect(['list'], Permission::PARAMETERS->value, SubPermission::LIST->value);
        $this->protect(['update'], Permission::PARAMETERS->value, SubPermission::UPDATE->value);
    }

    public function list(): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse([
            'parameters' => $this->service->list(),
        ]));
    }

    public function update(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            /** @var array<string, string|null> $values */
            $values = $request->input('values', []);

            $errors = $this->service->update($values);

            if ($errors !== []) {
                return $this->validationResponse($errors);
            }

            return $this->updatedResponse();
        });
    }
}
