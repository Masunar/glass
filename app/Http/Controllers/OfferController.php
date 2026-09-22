<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\Permission;
use Illuminate\Http\Request;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;
use App\Services\Offers\OfferBoard;
use App\Services\Offers\OfferService;

/**
 * Oferty: historia przy zleceniu i lista wszystkich.
 *
 * Wystawienie chodzi na `CREATE`, a decyzja klienta (przyjęcie,
 * odrzucenie, oznaczenie jako wysłana) na `UPDATE`. To nie jest
 * formalność: kto rozmawia z klientem, nie musi mieć prawa wystawiać
 * nowych dokumentów.
 */
class OfferController extends ApiController
{
    public function __construct(
        private readonly OfferBoard $board,
        private readonly OfferService $service,
    ) {
        $this->protect(
            ['index', 'forOrder'],
            Permission::OFFERS->value,
            SubPermission::LIST->value,
        );
        $this->protect(
            ['issue'],
            Permission::OFFERS->value,
            SubPermission::CREATE->value,
        );
        $this->protect(
            ['markSent', 'accept', 'reject'],
            Permission::OFFERS->value,
            SubPermission::UPDATE->value,
        );
    }

    public function index(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            /** @var array<string, mixed> $filters */
            $filters = $request->all();

            return $this->dataResponse($this->board->board($filters));
        });
    }

    public function forOrder(int $order): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->board->forOrder($order),
        ));
    }

    public function issue(Request $request, int $order): JsonResponse
    {
        return $this->secure(function () use ($request, $order): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->service->issue($order, $input);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse(['id' => $result['id'], 'number' => $result['number']]);
        });
    }

    public function markSent(int $order, int $offer): JsonResponse
    {
        return $this->secure(function () use ($order, $offer): JsonResponse {
            $result = $this->service->markSent($order, $offer);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->updatedResponse();
        });
    }

    public function accept(Request $request, int $order, int $offer): JsonResponse
    {
        return $this->secure(function () use ($request, $order, $offer): JsonResponse {
            $result = $this->service->accept($order, $offer, $request->input('accepted_list_id'));

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->updatedResponse();
        });
    }

    public function reject(Request $request, int $order, int $offer): JsonResponse
    {
        return $this->secure(function () use ($request, $order, $offer): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->service->reject($order, $offer, $input);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->updatedResponse();
        });
    }
}
