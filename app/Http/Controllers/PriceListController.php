<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\Section;
use App\Enum\Permission;
use Illuminate\Http\Request;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use App\Services\PriceListService;
use Salvon\Controller\ApiController;

/**
 * Cennik — macierz współczynników narzutu produkt × sekcja cenowa.
 */
class PriceListController extends ApiController
{
    public function __construct(
        private readonly PriceListService $service,
    ) {
        $this->protect(['matrix'], Permission::PRICE_LIST->value, SubPermission::LIST->value);
        $this->protect(['update'], Permission::PRICE_LIST->value, SubPermission::UPDATE->value);
    }

    public function matrix(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            $section = Section::tryFrom((string) $request->query('section', Section::GLASS->value));

            if ($section === null) {
                return $this->validationResponse(['section' => ['Nie ma takiej sekcji asortymentu.']]);
            }

            $group = $request->query('group_id');

            return $this->dataResponse($this->service->matrix(
                $section,
                $group === null || $group === '' ? null : (int) $group,
            ));
        });
    }

    public function update(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            /** @var array<int, array<string, mixed>> $cells */
            $cells = $request->input('cells', []);

            $errors = $this->service->update($cells);

            if ($errors !== []) {
                return $this->validationResponse($errors);
            }

            return $this->updatedResponse();
        });
    }
}
