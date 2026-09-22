<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use App\Services\AuditTrail;
use App\Enum\InvestmentType;
use Illuminate\Support\Facades\Validator;

/**
 * Dane inwestycji przy zleceniu.
 *
 * Dwie liczby, od których zależy stawka VAT na fakturze: rodzaj
 * obiektu i jego powierzchnia użytkowa. Rodzaj wybiera limit
 * (art. 41 ust. 12b — dom jednorodzinny, lokal mieszkalny),
 * powierzchnia decyduje, czy limit jest przekroczony i w jakiej części.
 *
 * Trzymane na zleceniu, bo limit dotyczy obiektu, a nie pozycji
 * faktury — i bo jedno zlecenie to jedna budowa. Przy kilku zleceniach
 * na ten sam dom metraż wpisuje się ponownie; to świadomy kompromis,
 * osobny słownik inwestycji byłby na to właściwą odpowiedzią, gdyby
 * okazało się, że to częsty przypadek.
 *
 * Zmiana jest audytowana: przestawienie metrażu z 300 na 500 przenosi
 * część kwoty z 8 % na 23 % i musi mieć autora.
 */
final readonly class InvestmentService
{
    public function __construct(
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>}
     */
    public function save(int $orderId, array $input): array
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        $validator = Validator::make($input, [
            'investment_type' => ['nullable', 'string', 'in:house,flat'],
            'investment_area_m2' => ['nullable', 'numeric', 'min:0.01', 'max:99999.99'],
        ], [
            'investment_type.in' => 'Nie znam takiego rodzaju inwestycji.',
            'investment_area_m2.min' => 'Powierzchnia musi być większa od zera.',
            'investment_area_m2.max' => 'Ta powierzchnia wygląda na pomyłkę.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages];
        }

        $type = InvestmentType::tryFrom((string) ($input['investment_type'] ?? ''));
        $area = $input['investment_area_m2'] ?? null;

        // Bez rodzaju obiektu metraz nie ma czego przekroczyc — limit
        // bierze sie wlasnie z rodzaju. Zostawiony sam wygladalby na
        // dana, ktora na cos wplywa, a nie wplywa na nic.
        if ($type === null) {
            $area = null;
        }

        $before = $this->describe($order);

        $order->update([
            'investment_type' => $type?->value,
            'investment_area_m2' => $area === null || $area === '' ? null : (float) $area,
        ]);

        $after = $this->describe($order->refresh());

        if ($before !== $after) {
            $this->audit->write(
                Order::class,
                (int) $order->getKey(),
                [['field' => 'inwestycja', 'before' => $before, 'after' => $after]],
                'investment_changed',
            );
        }

        return ['errors' => []];
    }

    private function describe(Order $order): ?string
    {
        $type = $order->investment_type;

        if ($type === null) {
            return null;
        }

        return $order->investment_area_m2 === null
            ? $type->label() . ', metraż nieznany'
            : $type->label() . ', ' . rtrim(rtrim((string) $order->investment_area_m2, '0'), '.') . ' m²';
    }
}
