<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use App\Enum\ListRole;
use App\Models\Status;
use App\Models\Location;
use App\Models\OrderList;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Models\InvoiceType;
use App\Support\Normalize;
use App\Enum\DeliveryMethod;
use App\Services\AuditTrail;
use App\Services\NumberSequence;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Zakładanie zlecenia.
 *
 * Zlecenie powstaje z jednej decyzji — „ten klient coś u nas zamawia" —
 * i wszystko poza kontrahentem oraz sposobem wydania da się uzupełnić
 * później. Formularz zakładania, który wymaga terminu i wyceny, kończy
 * się tym, że handlowiec wpisuje datę z sufitu, żeby przejść dalej.
 *
 * Zlecenie dostaje od razu pierwszą listę. Lista bez pozycji nic nie
 * kosztuje, a zlecenie bez listy nie ma gdzie przyjąć formatki i nie
 * przejdzie dalej — użytkownik musiałby odgadnąć, że najpierw trzeba
 * „dodać wycenę".
 */
final readonly class OrderService
{
    /** Numeracja przeniesiona ze starego systemu zaczyna się powyżej tej wartości. */
    private const FIRST_NUMBER = 24000;

    public function __construct(
        private NumberSequence $numbers = new NumberSequence(),
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * Słowniki potrzebne formularzowi. Punkt odbioru to nie każda
     * lokalizacja — hala produkcyjna nie musi wydawać towaru.
     *
     * @return array<string, mixed>
     */
    public function formOptions(): array
    {
        /** @var iterable<Location> $locations */
        $locations = Location::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        $branches = [];
        $pickupPoints = [];
        $defaultBranch = null;

        foreach ($locations as $location) {
            $row = ['id' => (int) $location->getKey(), 'name' => $location->name];
            $branches[] = $row;

            if ($location->is_pickup_point) {
                $pickupPoints[] = $row;
            }

            if ($location->is_default && $defaultBranch === null) {
                $defaultBranch = (int) $location->getKey();
            }
        }

        /** @var iterable<InvoiceType> $types */
        $types = InvoiceType::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        $invoiceTypes = [];
        $defaultInvoiceType = null;

        foreach ($types as $type) {
            $invoiceTypes[] = [
                'id' => (int) $type->getKey(),
                'name' => $type->name,
                'vat_rate' => $type->vat_rate,
            ];

            if ($type->is_default && $defaultInvoiceType === null) {
                $defaultInvoiceType = (int) $type->getKey();
            }
        }

        $status = $this->initialStatus();

        return [
            'branches' => $branches,
            'pickup_points' => $pickupPoints,
            'invoice_types' => $invoiceTypes,
            'defaults' => [
                'branch_id' => $defaultBranch,
                'invoice_type_id' => $defaultInvoiceType,
                'delivery_method' => DeliveryMethod::PICKUP->value,
            ],
            'status' => $status?->name,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null, number: int|null}
     */
    public function create(array $input): array
    {
        $errors = $this->validate($input);

        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null, 'number' => null];
        }

        $status = $this->initialStatus();

        if ($status === null) {
            return [
                'errors' => ['status' => ['Katalog statusów nie ma statusu początkowego zlecenia.']],
                'id' => null,
                'number' => null,
            ];
        }

        $method = DeliveryMethod::from((string) $input['delivery_method']);
        $number = $this->nextNumber();

        /** @var Order $order */
        $order = Order::query()->create([
            'number' => $number,
            'contractor_id' => (int) $input['contractor_id'],
            'status_id' => (int) $status->getKey(),
            'location_id' => $this->id($input['location_id'] ?? null),
            'delivery_method' => $method->value,
            // Punkt odbioru ma sens wylacznie przy odbiorze wlasnym —
            // przy montazu i dowozie jedziemy do klienta.
            'pickup_location_id' => $method === DeliveryMethod::PICKUP
                ? $this->id($input['pickup_location_id'] ?? null)
                : null,
            'delivery_address' => $method === DeliveryMethod::PICKUP
                ? null
                : Normalize::text($input['delivery_address'] ?? null),
            'delivery_contact' => $method === DeliveryMethod::PICKUP
                ? null
                : Normalize::text($input['delivery_contact'] ?? null),
            'invoice_type_id' => $this->id($input['invoice_type_id'] ?? null),
            'client_deadline' => Normalize::text($input['client_deadline'] ?? null),
            'short_note' => Normalize::text($input['short_note'] ?? null),
            'created_by' => Auth::id(),
        ]);

        OrderList::query()->create([
            'order_id' => (int) $order->getKey(),
            'number' => 1,
            'role' => ListRole::COMPONENT->value,
            'is_included' => true,
        ]);

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [
                ['field' => 'number', 'before' => null, 'after' => $number],
                ['field' => 'status', 'before' => null, 'after' => $status->code],
            ],
            'created',
        );

        return ['errors' => [], 'id' => (int) $order->getKey(), 'number' => $number];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, list<string>>
     */
    private function validate(array $input): array
    {
        $method = DeliveryMethod::tryFrom((string) ($input['delivery_method'] ?? ''));

        if ($method === null) {
            return ['delivery_method' => ['Wskaż sposób wydania.']];
        }

        $rules = [
            'contractor_id' => ['required', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'invoice_type_id' => ['nullable', 'integer'],
            'client_deadline' => ['nullable', 'date'],
            'short_note' => ['nullable', 'string', 'max:200'],
            'delivery_address' => ['nullable', 'string', 'max:200'],
            'delivery_contact' => ['nullable', 'string', 'max:120'],
        ];

        // Odbior wlasny bez punktu i dowoz bez adresu to zlecenia, ktore
        // zatrzymaja sie dopiero na wydaniu — czyli w najgorszym momencie.
        if ($method === DeliveryMethod::PICKUP) {
            $rules['pickup_location_id'] = ['required', 'integer'];
        } else {
            $rules['delivery_address'] = ['required', 'string', 'max:200'];
        }

        $validator = Validator::make($input, $rules, [
            'contractor_id.required' => 'Wskaż kontrahenta.',
            'pickup_location_id.required' => 'Wskaż punkt odbioru.',
            'delivery_address.required' => 'Podaj adres wydania.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return $messages;
        }

        return $this->checkReferences($input, $method);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, list<string>>
     */
    private function checkReferences(array $input, DeliveryMethod $method): array
    {
        $errors = [];

        if (!Contractor::query()->whereKey((int) $input['contractor_id'])->exists()) {
            $errors['contractor_id'] = ['Takiego kontrahenta nie ma w kartotece.'];
        }

        $branch = $this->id($input['location_id'] ?? null);

        if ($branch !== null && !Location::query()->whereKey($branch)->exists()) {
            $errors['location_id'] = ['Taka lokalizacja nie istnieje.'];
        }

        $invoiceType = $this->id($input['invoice_type_id'] ?? null);

        if ($invoiceType !== null && !InvoiceType::query()->whereKey($invoiceType)->exists()) {
            $errors['invoice_type_id'] = ['Taki typ faktury nie istnieje.'];
        }

        if ($method === DeliveryMethod::PICKUP) {
            $pickup = $this->id($input['pickup_location_id'] ?? null);

            $exists = $pickup !== null && Location::query()
                ->whereKey($pickup)
                ->where('is_pickup_point', true)
                ->exists();

            if (!$exists) {
                $errors['pickup_location_id'] = ['To miejsce nie wydaje towaru.'];
            }
        }

        return $errors;
    }

    /**
     * Numer zlecenia z pilnowaniem, żeby nie trafić w istniejący.
     *
     * Ciąg numeracji może zostać założony później niż same zlecenia —
     * po imporcie starej bazy, po ręcznym wgraniu danych albo gdy ktoś
     * numerował z innej domeny. Sam unikalny indeks też by to złapał,
     * ale użytkownik zobaczyłby „wystąpił nieoczekiwany błąd" zamiast
     * założonego zlecenia.
     */
    private function nextNumber(): int
    {
        $number = $this->numbers->next(NumberSequence::ORDERS, self::FIRST_NUMBER);
        $highest = (int) Order::query()->max('number');

        if ($number > $highest) {
            return $number;
        }

        $this->numbers->seedFrom(NumberSequence::ORDERS, $highest);

        return $this->numbers->next(NumberSequence::ORDERS, self::FIRST_NUMBER);
    }

    /**
     * Status początkowy jest danymi, nie stałą w kodzie: katalog statusów
     * wskazuje go flagą `is_default`.
     */
    private function initialStatus(): ?Status
    {
        /** @var Status|null */
        return Status::query()
            ->where('domain', StatusDomain::ORDER->value)
            ->where('is_default', true)
            ->orderBy('position')
            ->first();
    }

    private function id(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        return (int) $value;
    }
}
