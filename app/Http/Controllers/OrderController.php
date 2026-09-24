<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use App\Services\Orders\OrderCard;
use App\Services\Orders\OrderService;
use App\Services\Orders\OrderTransition;
use App\Services\Orders\OrderItemService;
use App\Services\Orders\OrderListService;
use App\Services\Orders\InvestmentService;
use App\Services\Orders\OrderFittingService;
use App\Services\Orders\OrderDiscountService;
use App\Services\Orders\OrderDrawingService;
use App\Services\Orders\OrderBoardService;
use App\Services\Orders\OrderOwnerService;
use App\Services\UserPreferences;
use App\Models\User;
use App\Services\Orders\PaymentService;

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
        private readonly OrderDrawingService $drawingService,
        private readonly PaymentService $paymentService,
        private readonly OrderListService $listService,
        private readonly OrderFittingService $fittingService,
        private readonly InvestmentService $investmentService,
        private readonly OrderOwnerService $ownerService,
        private readonly UserPreferences $preferences,
    ) {
        $this->protect(
            ['board', 'alerts', 'card', 'items', 'drawings', 'drawingFile', 'payments', 'previewPane'],
            Permission::ORDERS->value,
            SubPermission::LIST->value,
        );
        $this->protect(
            ['formOptions', 'create'],
            Permission::ORDERS->value,
            SubPermission::CREATE->value,
        );
        $this->protect(
            [
                'transition', 'savePane', 'saveService', 'saveDiscounts',
                'addDrawing', 'declareDrawings', 'addPayment', 'reversePayment',
                'saveList', 'moveItem', 'saveFitting', 'addFittingSet',
                'saveInvestment', 'changeOwner',
            ],
            Permission::ORDERS->value,
            SubPermission::UPDATE->value,
        );
        $this->protect(
            ['deleteItem', 'deleteDrawing', 'deleteList'],
            Permission::ORDERS->value,
            SubPermission::DELETE->value,
        );
    }

    public function board(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            $query = $request->query('q');
            $status = $request->query('status');
            $phase = $request->query('phase');

            // „Moje" nie przyjmuje cudzego identyfikatora: to
            // przelacznik na wlasna liste, a nie podglad czyjejs.
            // Ogladanie zlecen kolegi odbywa sie bez filtra — kazdy
            // widzi wszystkie.
            $mine = $request->boolean('mine');
            $me = $mine ? $request->user()?->getKey() : null;

            // Liczba wierszy na strone: z zadania, jesli dozwolona,
            // inaczej zapamietana przy koncie.
            $user = $request->user();
            $perPage = $this->preferences->resolve(
                $user instanceof User ? $user : null,
                UserPreferences::ORDERS_PER_PAGE,
                $request->query('per_page'),
            );

            return $this->dataResponse($this->board->board(
                is_string($query) ? $query : null,
                is_string($status) ? $status : null,
                limit: $perPage,
                ownerId: is_numeric($me) ? (int) $me : null,
                page: max(1, $request->integer('page', 1)),
                phase: is_string($phase) && $phase !== '' ? $phase : null,
                // `alerts=0` — ekran dociaga alerty osobnym zapytaniem.
                withAlerts: $request->query('alerts') !== '0',
            ));
        });
    }

    /** Znaczniki alertów pokazanych zleceń i liczniki przy zakładkach. */
    public function alerts(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            $raw = $request->query('ids');
            $ids = is_string($raw) ? explode(',', $raw) : (is_array($raw) ? $raw : []);

            // Strona listy ma najwyzej dwiescie wierszy — wiecej to nie
            // strona, tylko proba wyciagniecia calej bazy tym wejsciem.
            $ids = array_slice(array_values(array_unique(array_map(
                intval(...),
                array_filter($ids, is_numeric(...)),
            ))), 0, 200);

            $me = $request->boolean('mine') ? $request->user()?->getKey() : null;

            return $this->dataResponse($this->board->alerts(
                $ids,
                ownerId: is_numeric($me) ? (int) $me : null,
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

    /**
     * Podgląd wyceny formatki bez zapisu. Ta sama droga, którą idzie
     * zapis — inaczej podgląd pokazywałby kwotę, której zapis nie
     * potwierdzi.
     */
    public function previewPane(Request $request, int $order): JsonResponse
    {
        return $this->secure(function () use ($request, $order): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            return $this->dataResponse($this->itemService->preview($order, $input));
        });
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
     * Okucie na zleceniu. Cena idzie z cennika — pole ceny w formularzu
     * jest nadpisaniem, nie źródłem.
     */
    public function saveFitting(Request $request, int $order, ?int $item = null): JsonResponse
    {
        return $this->secure(function () use ($request, $order, $item): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->fittingService->save($order, $input, $item);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse(['id' => $result['id']]);
        });
    }

    /** Rozwinięcie zestawu na pozycje listy. */
    public function addFittingSet(Request $request, int $order): JsonResponse
    {
        return $this->secure(function () use ($request, $order): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->fittingService->addSet($order, $input);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse(['ids' => $result['ids']]);
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

    /** Rysunki zlecenia razem z oświadczeniem o komplecie. */
    public function drawings(int $order): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->drawingService->board($order),
        ));
    }

    public function addDrawing(Request $request, int $order): JsonResponse
    {
        return $this->secure(function () use ($request, $order): JsonResponse {
            $result = $this->drawingService->store(
                $order,
                $request->file('file') instanceof UploadedFile ? $request->file('file') : null,
                $request->input('order_item_id'),
                $request->input('note'),
            );

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse(['id' => $result['id']]);
        });
    }

    /**
     * Plik idzie strumieniem przez aplikację, a nie z katalogu publicznego —
     * rysunek techniczny klienta nie ma leżeć pod zgadywalnym adresem.
     */
    public function drawingFile(int $order, int $drawing): HttpResponse
    {
        return $this->secure(function () use ($order, $drawing): HttpResponse {
            $file = $this->drawingService->file($order, $drawing);

            if ($file === null) {
                return $this->notFoundResponse();
            }

            // Pobrany plik ma sie nazywac tak, jak go wgrano — na dysku
            // lezy pod nazwa wygenerowana, zeby dwa "rysunek.pdf" sie nie
            // nadpisaly, ale czlowiekowi to nic nie mowi.
            return response()->download(
                $this->drawingService->path($file),
                $file->original_name,
            );
        });
    }

    public function deleteDrawing(int $order, int $drawing): JsonResponse
    {
        return $this->secure(function () use ($order, $drawing): JsonResponse {
            $result = $this->drawingService->delete($order, $drawing);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->deletedResponse();
        });
    }

    public function declareDrawings(Request $request, int $order): JsonResponse
    {
        return $this->secure(function () use ($request, $order): JsonResponse {
            $result = $this->drawingService->declare($order, $request->boolean('complete'));

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->updatedResponse();
        });
    }

    /**
     * Przekazanie zlecenia innej osobie. Własna akcja, bo to decyzja —
     * w tym kontrolerze każda ma swoją, zamiast jednego wspólnego PUT,
     * po którym nie wiadomo, co się zmieniło.
     */
    public function changeOwner(Request $request, int $order): JsonResponse
    {
        return $this->secure(function () use ($request, $order): JsonResponse {
            $result = $this->ownerService->change($order, $request->input('owner_id'));

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->updatedResponse();
        });
    }

    /** Wpłaty do zlecenia razem z saldem i limitem kupieckim. */
    public function payments(int $order): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->paymentService->board($order),
        ));
    }

    public function addPayment(Request $request, int $order): JsonResponse
    {
        return $this->secure(function () use ($request, $order): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->paymentService->store($order, $input);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse(['id' => $result['id']]);
        });
    }

    /**
     * Korekta wpłaty. Świadomie pod POST, a nie DELETE: nic nie znika,
     * dopisujemy wiersz odwrotny.
     */
    public function reversePayment(Request $request, int $order, int $payment): JsonResponse
    {
        return $this->secure(function () use ($request, $order, $payment): JsonResponse {
            $result = $this->paymentService->reverse($order, $payment, $request->input('note'));

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse(['id' => $result['id']]);
        });
    }

    /**
     * Lista zlecenia. Obsługuje kompozycję (kilka pomieszczeń) i
     * wariantowanie oferty — stąd rola i osobne przełączniki „wchodzi
     * do kwoty" oraz „wstrzymana".
     */
    public function saveList(Request $request, int $order, ?int $list = null): JsonResponse
    {
        return $this->secure(function () use ($request, $order, $list): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->listService->save($order, $input, $list);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse(['id' => $result['id']]);
        });
    }

    /**
     * Dane inwestycji — rodzaj obiektu i powierzchnia użytkowa.
     *
     * Osobny zapis, a nie pole w formularzu zakładania: metraż znany
     * jest zwykle później niż zlecenie, a decyduje o stawce VAT na
     * fakturze, więc musi dać się poprawić bez ruszania reszty karty.
     */
    public function saveInvestment(Request $request, int $order): JsonResponse
    {
        return $this->secure(function () use ($request, $order): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->investmentService->save($order, $input);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->updatedResponse();
        });
    }

    public function deleteList(int $order, int $list): JsonResponse
    {
        return $this->secure(function () use ($order, $list): JsonResponse {
            $result = $this->listService->delete($order, $list);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->deletedResponse();
        });
    }

    /** Przeniesienie pozycji na inną listę — bez przeliczania ceny. */
    public function moveItem(Request $request, int $order, int $item): JsonResponse
    {
        return $this->secure(function () use ($request, $order, $item): JsonResponse {
            $result = $this->listService->moveItem($order, $item, $request->input('order_list_id'));

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
