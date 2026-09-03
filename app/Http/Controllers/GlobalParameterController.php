<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\Permission;
use Illuminate\Http\Request;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;
use App\Services\ParameterImpact;
use App\Services\GlobalParameterService;

/**
 * Parametry wzoru wyceny i teksty ofertowe — odpowiednik zakładki
 * „Ogólne" w słownikach starego systemu.
 */
class GlobalParameterController extends ApiController
{
    public function __construct(
        private readonly GlobalParameterService $service,
        private readonly ParameterImpact $impact,
    ) {
        $this->protect(['list'], Permission::PARAMETERS->value, SubPermission::LIST->value);
        $this->protect(['update'], Permission::PARAMETERS->value, SubPermission::UPDATE->value);
        $this->protect(['preview'], Permission::PARAMETERS->value, SubPermission::LIST->value);
        $this->protect(['history'], Permission::PARAMETERS->value, SubPermission::LIST->value);
    }

    public function list(): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->service->board(),
        ));
    }

    /**
     * O ile wpisane, jeszcze niezapisane wartosci ruszylyby ceny.
     *
     * Sam ekran nie umialby tego policzyc - wzor wyceny zyje po stronie
     * serwera i to on jest jedynym miejscem, ktore zna jego kolejnosc.
     */
    public function preview(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            /** @var array<string, string|null> $values */
            $values = $request->input('values', []);

            return $this->dataResponse($this->impact->preview($values));
        });
    }

    /** Historia zapisow zestawu parametrow. */
    public function history(): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse([
            'entries' => $this->service->history(),
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
