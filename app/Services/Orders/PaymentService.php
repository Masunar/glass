<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Normalize;
use App\Models\CashRegister;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Wpłaty do zlecenia.
 *
 * **Rejestr, nie lista do kasowania.** Stary system pozwalał wpłatę
 * dodać i nie pokazywał jej potem nigdzie — nie było ani listy, ani
 * korekty. Tutaj wpłaty się nie usuwa: korekta jest osobnym wierszem
 * z kwotą ujemną, wskazującym na wpłatę, którą odwraca. Historia
 * zostaje w całości, bo to ona jest dowodem przy sporze o pieniądze.
 *
 * **Waluta pochodzi z kasy**, a nie z listy wpisanej w kodzie: słownik
 * kas zna PLN, EUR i USD, więc twarda lista dwóch walut w kodzie
 * rozjechałaby się z danymi przy pierwszej nowej kasie.
 *
 * **Kurs podaje człowiek.** Księgowa przepisuje go z wyciągu bankowego;
 * automat z zewnętrznej tabeli dawałby kwotę inną niż na dokumencie,
 * po którym pieniądze faktycznie weszły.
 */
final readonly class PaymentService
{
    /** Waluta, w której liczone jest saldo zlecenia. */
    public const BASE_CURRENCY = 'PLN';

    public function __construct(
        private OrderValue $value = new OrderValue(),
        private ContractorBalance $balance = new ContractorBalance(),
        private OrderTabs $tabs = new OrderTabs(),
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function board(int $orderId): array
    {
        /** @var Order $order */
        $order = Order::query()
            ->with([
                'contractor',
                'status',
                'invoiceType',
                'discounts',
                'lists.items.processes',
                'payments.cashRegister',
                'payments.creator',
            ])
            ->findOrFail($orderId);

        $totals = $this->value->totals($order);
        $rows = [];
        $paid = 0.0;
        $reversed = [];

        foreach ($order->payments as $payment) {
            if ($payment->reversal_of_id !== null) {
                $reversed[(int) $payment->reversal_of_id] = true;
            }
        }

        foreach ($order->payments->sortByDesc('paid_on') as $payment) {
            $paid += (float) $payment->amount_base;

            $rows[] = [
                'id' => (int) $payment->getKey(),
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'exchange_rate' => $payment->exchange_rate,
                'amount_base' => $payment->amount_base,
                'paid_on' => $payment->paid_on->toDateString(),
                'register' => $payment->cashRegister?->name,
                'note' => $payment->note,
                'is_reversal' => $payment->isReversal(),
                'reverses_id' => $payment->reversal_of_id,
                'is_reversed' => isset($reversed[(int) $payment->getKey()]),
                'by' => $this->name($payment),
            ];
        }

        $gross = $totals->gross;
        $due = $gross === null ? null : round((float) $gross - $paid, 2);

        return [
            'order' => [
                'id' => (int) $order->getKey(),
                'number' => (int) $order->number,
                'contractor' => $order->contractor?->displayName(),
            ],
            'tabs' => $this->tabs->counts($order),
            'payments' => $rows,
            'summary' => [
                'net' => $totals->net,
                'vat_rate' => $totals->vatRate,
                // Bez typu faktury nie znamy stawki, wiec nie znamy tez
                // kwoty do zaplaty. Zero byloby klamstwem.
                'gross' => $gross,
                'paid' => $this->money($paid),
                'due' => $due === null ? null : $this->money($due),
                'paid_percent' => $gross === null || (float) $gross <= 0.0
                    ? null
                    : (int) round($paid / (float) $gross * 100),
            ],
            'credit' => $order->contractor === null ? null : [
                'limit' => $order->contractor->credit_limit,
                'outstanding' => $this->balance->outstanding($order->contractor),
                'payment_days' => $order->contractor->payment_days,
            ],
            'registers' => $this->registers(),
            'base_currency' => self::BASE_CURRENCY,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    public function store(int $orderId, array $input): array
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        $validator = Validator::make($input, [
            'cash_register_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'paid_on' => ['required', 'date'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:200'],
        ], [
            'cash_register_id.required' => 'Wskaż kasę.',
            'amount.required' => 'Podaj kwotę.',
            'amount.not_in' => 'Wpłata na zero nic nie zmienia.',
            'paid_on.required' => 'Podaj datę wpłaty.',
            'exchange_rate.gt' => 'Kurs musi być większy od zera.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages, 'id' => null];
        }

        /** @var CashRegister|null $register */
        $register = CashRegister::query()
            ->where('is_active', true)
            ->find((int) $input['cash_register_id']);

        if ($register === null) {
            return ['errors' => ['cash_register_id' => ['Taka kasa nie istnieje.']], 'id' => null];
        }

        $currency = $register->default_currency;
        $rate = $this->rate($input, $currency);

        if ($rate === null) {
            return [
                'errors' => ['exchange_rate' => [sprintf(
                    'Kasa rozlicza się w %s — podaj kurs, po którym przeliczyć wpłatę na %s.',
                    $currency,
                    self::BASE_CURRENCY,
                )]],
                'id' => null,
            ];
        }

        $amount = round((float) $input['amount'], 2);

        /** @var Payment $payment */
        $payment = Payment::query()->create([
            'order_id' => (int) $order->getKey(),
            'cash_register_id' => (int) $register->getKey(),
            'amount' => $this->money($amount),
            'currency' => $currency,
            'exchange_rate' => number_format($rate, 6, '.', ''),
            'amount_base' => $this->money($amount * $rate),
            'paid_on' => Carbon::parse((string) $input['paid_on'])->toDateString(),
            'note' => Normalize::text($input['note'] ?? null),
            'created_by' => Auth::id(),
        ]);

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [[
                'field' => 'wpłata',
                'before' => null,
                'after' => sprintf('%s %s (%s)', $payment->amount, $currency, $register->name),
            ]],
            'payment_added',
        );

        return ['errors' => [], 'id' => (int) $payment->getKey()];
    }

    /**
     * Storno: nowy wiersz z kwotą ujemną, wskazujący na odwracaną
     * wpłatę. Sam wiersz odwracany zostaje nietknięty — to jest sens
     * korekty i jedyny sposób, żeby historia pieniędzy się zgadzała.
     *
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    public function reverse(int $orderId, int $paymentId, mixed $note): array
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        /** @var Payment|null $original */
        $original = Payment::query()
            ->where('order_id', $order->getKey())
            ->find($paymentId);

        if ($original === null) {
            return ['errors' => ['payment' => ['Ta wpłata nie należy do tego zlecenia.']], 'id' => null];
        }

        if ($original->isReversal()) {
            return ['errors' => ['payment' => ['Korekty nie koryguje się drugi raz.']], 'id' => null];
        }

        if (Payment::query()->where('reversal_of_id', $original->getKey())->exists()) {
            return ['errors' => ['payment' => ['Ta wpłata została już skorygowana.']], 'id' => null];
        }

        $reversal = DB::transaction(function () use ($order, $original, $note): Payment {
            /** @var Payment */
            return Payment::query()->create([
                'order_id' => (int) $order->getKey(),
                'cash_register_id' => $original->cash_register_id,
                'amount' => $this->money(-(float) $original->amount),
                'currency' => $original->currency,
                'exchange_rate' => $original->exchange_rate,
                // Kurs bierzemy z wplaty odwracanej, nie z dnia korekty:
                // korekta ma wyzerowac dokladnie te kwote, ktora weszla.
                'amount_base' => $this->money(-(float) $original->amount_base),
                'paid_on' => Carbon::now()->toDateString(),
                'note' => Normalize::text(is_string($note) ? $note : null),
                'reversal_of_id' => (int) $original->getKey(),
                'created_by' => Auth::id(),
            ]);
        });

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [[
                'field' => 'korekta wpłaty #' . $original->getKey(),
                'before' => $original->amount . ' ' . $original->currency,
                'after' => $reversal->note ?? 'storno',
            ]],
            'payment_reversed',
        );

        return ['errors' => [], 'id' => (int) $reversal->getKey()];
    }

    /**
     * Kurs: dla waluty rozliczeniowej zawsze jeden, dla pozostałych
     * wpisany ręcznie. `null` oznacza, że go brakuje.
     *
     * @param array<string, mixed> $input
     */
    private function rate(array $input, string $currency): ?float
    {
        if ($currency === self::BASE_CURRENCY) {
            return 1.0;
        }

        $raw = $input['exchange_rate'] ?? null;

        if ($raw === null || $raw === '' || (float) $raw <= 0.0) {
            return null;
        }

        return (float) $raw;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function registers(): array
    {
        $rows = [];

        /** @var iterable<CashRegister> $registers */
        $registers = CashRegister::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        foreach ($registers as $register) {
            $rows[] = [
                'id' => (int) $register->getKey(),
                'name' => $register->name,
                'currency' => $register->default_currency,
                'needs_rate' => $register->default_currency !== self::BASE_CURRENCY,
            ];
        }

        return $rows;
    }

    private function name(Payment $payment): ?string
    {
        $user = $payment->creator;

        if ($user === null) {
            return null;
        }

        $name = trim((string) $user->first_name . ' ' . (string) $user->last_name);

        return $name === '' ? null : $name;
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
