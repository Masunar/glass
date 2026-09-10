<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\Permission;
use Illuminate\Http\Request;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;
use App\Services\Orders\OrderCard;
use App\Services\Orders\OrderTransition;
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
    ) {
        $this->protect(['board', 'card'], Permission::ORDERS->value, SubPermission::LIST->value);
        $this->protect(['transition'], Permission::ORDERS->value, SubPermission::UPDATE->value);
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

    public function card(int $order): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->cardService->card($order),
        ));
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
