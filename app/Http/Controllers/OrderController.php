<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\Permission;
use Illuminate\Http\Request;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;
use App\Services\Orders\OrderCard;
use App\Services\Orders\OrderService;
use App\Services\Orders\OrderTransition;
use App\Services\Orders\OrderItemService;
use App\Services\Orders\OrderDiscountService;
use App\Services\Orders\OrderBoardService;

/**
 * Zlecenia: lista w pasmach pilności i karta pojedynczego zlecenia.
 */
class OrderController extends ApiController
{
    public function __construct(
        private readonly OrderBoardService $board,
        private readonly OrderCard $cardService,
        private readonly OrderTransition $transitionService,
        private readonly OrderService $service,
        private readonly OrderItemService $itemService,
        private readonly OrderDiscountService $discountService,
    ) {
        $this->protect(['board', 'card', 'items'], Permission::ORDERS->value, SubPermission::LIST->value);
        $this->protect(
            ['formOptions', 'create'],
            Permission::ORDERS->value,
            SubPermission::CREATE->value,
        );
        $this->protect(
            ['transition', 'savePane', 'saveService', 'saveDiscounts'],
            Permission::ORDERS->value,
            SubPermission::UPDATE->value,
        );
        $this->protect(['deleteItem'], Permission::ORDERS->value, SubPermission::DELETE->value);
    }

    public function board(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            $query = $request->query('q');
            $status = $request->query('status');

            return $this->dataResponse($this->board->board(
                is_string($query) ? $query : null,
                is_string($status) ? $status : null,
            ));
        });
    }

    /** Słowniki formularza zakładania zlecenia. */
    public function formOptions(): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->service->formOptions(),
        ));
    }

    public function create(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->service->create($input);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse([
                'id' => $result['id'],
                'number' => $result['number'],
            ]);
        });
    }

    public function card(int $order): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->cardService->card($order),
        ));
    }

    /** Ekran formatek: listy z pozycjami, sumy i słowniki formularza. */
    public function items(int $order): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->itemService->board($order),
        ));
    }

    public function savePane(Request $request, int $order, ?int $item = null): JsonResponse
    {
        return $this->secure(function () use ($request, $order, $item): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->itemService->savePane($order, $input, $item);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse(['id' => $result['id']]);
        });
    }

    public function saveService(Request $request, int $order, ?int $item = null): JsonResponse
    {
        return $this->secure(function () use ($request, $order, $item): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->itemService->saveService($order, $input, $item);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse(['id' => $result['id']]);
        });
    }

    /**
     * Rabat na zleceniu. Limit roli jest sprawdzany po stronie serwera —
     * ekran pokazuje go tylko po to, żeby nie trzeba było zgadywać.
     */
    public function saveDiscounts(Request $request, int $order): JsonResponse
    {
        return $this->secure(function () use ($request, $order): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->input('discounts', []);

            $result = $this->discountService->save($order, $input);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->updatedResponse();
        });
    }

    public function deleteItem(int $order, int $item): JsonResponse
    {
        return $this->secure(function () use ($order, $item): JsonResponse {
            $result = $this->itemService->delete($order, $item);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->deletedResponse();
        });
    }

    /**
     * Przejście statusu wykonywane zarówno z karty, jak i z kolumny
     * „co dalej" na liście — dlatego odpowiedź niesie nowy status,
     * a nie tylko potwierdzenie.
     */
    public function transition(Request $request, int $order): JsonResponse
    {
        return $this->secure(function () use ($request, $order): JsonResponse {
            $reason = $request->input('reason');

            $result = $this->transitionService->run(
                $order,
                (int) $request->input('transition_id'),
                is_string($reason) && trim($reason) !== '' ? trim($reason) : null,
            );

            if ($result['errors'] !== []) {
                return $this->validationResponse(['transition' => $result['errors']]);
            }

            return $this->dataResponse([
                'status' => $result['status'],
                'status_code' => $result['status_code'],
            ]);
        });
    }
}
