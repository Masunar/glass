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
use App\Services\Offers\OfferMail;
use App\Services\Offers\OfferDocument;
use App\Models\Offer;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

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
        private readonly OfferMail $mail,
        private readonly OfferDocument $document,
    ) {
        $this->protect(
            ['index', 'forOrder', 'pdf', 'mailPreview'],
            Permission::OFFERS->value,
            SubPermission::LIST->value,
        );
        $this->protect(
            ['issue'],
            Permission::OFFERS->value,
            SubPermission::CREATE->value,
        );
        $this->protect(
            ['markSent', 'accept', 'reject', 'send'],
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

    /**
     * Wydruk oferty.
     *
     * Skladany z migawki przy kazdym pobraniu, a nie przechowywany:
     * migawka jest zamrozona, wiec wynik jest powtarzalny, a plik obok
     * niej bylby druga kopia tej samej prawdy.
     */
    public function pdf(int $order, int $offer): HttpResponse
    {
        return $this->secure(function () use ($order, $offer): HttpResponse {
            /** @var Offer|null $row */
            $row = Offer::query()->with('order')->where('order_id', $order)->find($offer);

            if ($row === null) {
                return $this->notFoundResponse();
            }

            return response()->streamDownload(
                function () use ($row): void {
                    echo $this->document->render($row);
                },
                $this->document->fileName($row),
                ['Content-Type' => 'application/pdf'],
            );
        });
    }

    /** Gotowa treść wiadomości do pokazania przed wysłaniem. */
    public function mailPreview(int $order, int $offer): JsonResponse
    {
        return $this->secure(function () use ($order, $offer): JsonResponse {
            $result = $this->mail->preview($order, $offer);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse($result['data'] ?? []);
        });
    }

    public function send(Request $request, int $order, int $offer): JsonResponse
    {
        return $this->secure(function () use ($request, $order, $offer): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->mail->send($order, $offer, $input);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->updatedResponse();
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
