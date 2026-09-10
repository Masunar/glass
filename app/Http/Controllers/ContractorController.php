<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\Permission;
use Illuminate\Http\Request;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use App\Services\ContractorService;
use Salvon\Controller\ApiController;

/**
 * Kartoteka kontrahentów.
 */
class ContractorController extends ApiController
{
    public function __construct(
        private readonly ContractorService $service,
    ) {
        $this->protect(['list', 'card'], Permission::CONTRACTORS->value, SubPermission::LIST->value);
        $this->protect(['create'], Permission::CONTRACTORS->value, SubPermission::CREATE->value);
        $this->protect(
            ['update', 'priceSections'],
            Permission::CONTRACTORS->value,
            SubPermission::UPDATE->value,
        );
    }

    public function list(Request $request): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse($this->service->list(
            $request->query('query') === null ? null : (string) $request->query('query'),
            $request->boolean('include_inactive'),
        )));
    }

    public function card(int $contractor): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->service->card($contractor),
        ));
    }

    public function create(Request $request): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->save($request, null));
    }

    public function update(Request $request, int $contractor): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->save($request, $contractor));
    }

    public function priceSections(Request $request, int $contractor): JsonResponse
    {
        return $this->secure(function () use ($request, $contractor): JsonResponse {
            /** @var array<string, mixed> $sections */
            $sections = $request->input('sections', []);

            $errors = $this->service->savePriceSections($contractor, $sections);

            if ($errors !== []) {
                return $this->validationResponse($errors);
            }

            return $this->updatedResponse();
        });
    }

    private function save(Request $request, ?int $contractorId): JsonResponse
    {
        /** @var array<string, mixed> $input */
        $input = $request->all();

        $result = $this->service->save($input, $contractorId);

        if ($result['errors'] !== []) {
            return $this->validationResponse($result['errors']);
        }

        return $this->dataResponse(['id' => $result['id']]);
    }
}
