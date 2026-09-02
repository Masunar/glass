<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\Permission;
use Illuminate\Http\Request;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;
use App\Services\ProductCatalogService;

/**
 * Kartoteka produktów i grup asortymentowych — edycja z ekranu cennika.
 */
class ProductCatalogController extends ApiController
{
    public function __construct(
        private readonly ProductCatalogService $service,
    ) {
        $this->protect(['createProduct', 'createGroup'], Permission::PRODUCTS->value, SubPermission::CREATE->value);
        $this->protect(['updateProduct', 'updateGroup'], Permission::PRODUCTS->value, SubPermission::UPDATE->value);
    }

    public function createProduct(Request $request): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->saveProduct($request, null));
    }

    public function updateProduct(Request $request, int $product): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->saveProduct($request, $product));
    }

    public function createGroup(Request $request): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->saveGroup($request, null));
    }

    public function updateGroup(Request $request, int $group): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->saveGroup($request, $group));
    }

    private function saveProduct(Request $request, ?int $productId): JsonResponse
    {
        /** @var array<string, mixed> $input */
        $input = $request->all();

        $result = $this->service->saveProduct($input, $productId);

        if ($result['errors'] !== []) {
            return $this->validationResponse($result['errors']);
        }

        return $this->dataResponse(['id' => $result['id']]);
    }

    private function saveGroup(Request $request, ?int $groupId): JsonResponse
    {
        /** @var array<string, mixed> $input */
        $input = $request->all();

        $result = $this->service->saveGroup($input, $groupId);

        if ($result['errors'] !== []) {
            return $this->validationResponse($result['errors']);
        }

        return $this->dataResponse(['id' => $result['id']]);
    }
}
