<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Enum\Permission;
use Illuminate\Http\Request;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;
use App\Services\Warehouse\PickingList;
use App\Services\Warehouse\WarehouseDocuments;
use App\Services\Warehouse\ExtraDeliveryService;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Kompletacja okuć i dostawy dodatkowe do zleceń (uwagi klienta
 * z 25.09, punkty 3c i 3d).
 */
class WarehousePickingController extends ApiController
{
    /** Domyślny zakres listy: dziś i sześć kolejnych dni. */
    private const DEFAULT_DAYS = 6;

    public function __construct(
        private readonly PickingList $picking,
        private readonly ExtraDeliveryService $extra,
        private readonly WarehouseDocuments $documents,
    ) {
        $this->protect(
            ['picking', 'pickingPdf', 'extraIndex'],
            Permission::WAREHOUSE->value,
            SubPermission::LIST->value,
        );
        $this->protect(
            ['prepare', 'unprepare', 'extraStore', 'extraReceive', 'extraCancel'],
            Permission::WAREHOUSE->value,
            SubPermission::UPDATE->value,
        );
    }

    public function picking(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            [$from, $to] = $this->range($request);

            return $this->dataResponse($this->picking->board($from, $to, Carbon::today()));
        });
    }

    public function pickingPdf(Request $request): HttpResponse
    {
        return $this->secure(function () use ($request): HttpResponse {
            [$from, $to] = $this->range($request);

            return response()->streamDownload(
                function () use ($from, $to): void {
                    echo $this->documents->picking($from, $to, Carbon::today());
                },
                sprintf('kompletacja-%s.pdf', $from->toDateString()),
                ['Content-Type' => 'application/pdf'],
            );
        });
    }

    public function prepare(int $order): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse($this->picking->mark($order, true)));
    }

    public function unprepare(int $order): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse($this->picking->mark($order, false)));
    }

    public function extraIndex(Request $request): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->extra->board((string) ($request->query('status') ?? 'open')),
        ));
    }

    public function extraStore(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->extra->create($input);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse(['id' => $result['id']]);
        });
    }

    public function extraReceive(Request $request, int $delivery): JsonResponse
    {
        return $this->secure(function () use ($request, $delivery): JsonResponse {
            $date = $request->input('received_at');
            $receivedAt = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
                ? Carbon::createFromFormat('Y-m-d', $date)
                : null;

            $result = $this->extra->receive($delivery, $receivedAt ?: null);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse([]);
        });
    }

    public function extraCancel(int $delivery): JsonResponse
    {
        return $this->secure(function () use ($delivery): JsonResponse {
            $result = $this->extra->cancel($delivery);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse([]);
        });
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request): array
    {
        $from = $this->date($request->query('from')) ?? Carbon::today();
        $to = $this->date($request->query('to')) ?? $from->copy()->addDays(self::DEFAULT_DAYS);

        return $to->lt($from) ? [$from, $from->copy()] : [$from, $to];
    }

    private function date(mixed $value): ?Carbon
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = Carbon::createFromFormat('Y-m-d', $value);

        return $date === null ? null : $date->startOfDay();
    }
}
