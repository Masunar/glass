<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Carbon\Carbon;
use App\Models\Order;
use App\Support\Normalize;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\Validator;

/**
 * Termin i komentarze zlecenia — poprawiane z karty.
 *
 * Do tej pory dało się je wpisać wyłącznie przy zakładaniu zlecenia,
 * a karta pokazywała „brak" bez możliwości uzupełnienia. Termin
 * klienta, przesunięty i powód przesunięcia są edytowalne zawsze
 * (decyzja Marcina, 25.09) — każda zmiana idzie do dziennika, bo termin
 * to rzecz, o którą po tygodniu pyta klient.
 */
final readonly class OrderDetailsService
{
    /** Pole API => kolumna i najdłuższy dopuszczalny tekst. */
    public const COMMENTS = [
        'short' => ['column' => 'short_note', 'label' => 'krótka uwaga', 'max' => 200],
        'production' => ['column' => 'production_comment', 'label' => 'komentarz dla produkcji', 'max' => 2000],
        'installer' => ['column' => 'installer_comment', 'label' => 'komentarz dla montażysty', 'max' => 2000],
        'offer' => ['column' => 'offer_comment', 'label' => 'komentarz na ofertę', 'max' => 2000],
    ];

    public function __construct(
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>}
     */
    public function deadline(int $orderId, array $input): array
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        $validator = Validator::make($input, [
            'client_deadline' => ['nullable', 'date_format:Y-m-d'],
            'shifted_deadline' => ['nullable', 'date_format:Y-m-d'],
            'shift_reason' => ['nullable', 'string', 'max:200'],
        ], [
            'client_deadline.date_format' => 'Podaj datę w formacie RRRR-MM-DD.',
            'shifted_deadline.date_format' => 'Podaj datę w formacie RRRR-MM-DD.',
            'shift_reason.max' => 'Powód przesunięcia zmieści się w 200 znakach.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $errors */
            $errors = $validator->errors()->toArray();

            return ['errors' => $errors];
        }

        $client = self::date($input['client_deadline'] ?? null);
        $shifted = self::date($input['shifted_deadline'] ?? null);
        $reason = Normalize::text($input['shift_reason'] ?? null);

        // Powod bez przesuniecia nie ma czego tlumaczyc — zostalby na
        // karcie jako zdanie o przesunieciu, ktorego nie ma.
        if ($shifted === null) {
            $reason = null;
        }

        $changes = [];

        foreach ([
            'termin klienta' => ['client_deadline', $client],
            'termin przesunięty' => ['shifted_deadline', $shifted],
        ] as $label => [$column, $value]) {
            /** @var Carbon|null $current */
            $current = $order->{$column};
            $before = $current?->toDateString();

            if ($before !== $value) {
                $changes[] = ['field' => $label, 'before' => $before, 'after' => $value];
                // Carbon, nie napis: kolumna jest rzutowana na date,
                // a model przyjmuje to, co sam zwraca.
                $order->{$column} = $value === null
                    ? null
                    : Carbon::createFromFormat('Y-m-d', $value)?->startOfDay();
            }
        }

        if ($order->shift_reason !== $reason) {
            $changes[] = ['field' => 'powód przesunięcia', 'before' => $order->shift_reason, 'after' => $reason];
            $order->shift_reason = $reason;
        }

        if ($changes === []) {
            return ['errors' => []];
        }

        $order->save();
        $this->audit->write(Order::class, (int) $order->getKey(), $changes, 'deadline_changed');

        return ['errors' => []];
    }

    /**
     * Jeden komentarz naraz — karta zapisuje pole, które właśnie
     * poprawiono, a nie cały formularz.
     *
     * @return array{errors: array<string, list<string>>}
     */
    public function comment(int $orderId, string $field, mixed $text): array
    {
        $definition = self::COMMENTS[$field] ?? null;

        if ($definition === null) {
            return ['errors' => ['field' => ['Nieznany komentarz.']]];
        }

        if ($text !== null && !is_string($text)) {
            return ['errors' => ['text' => ['Komentarz musi być tekstem.']]];
        }

        $value = Normalize::text($text);

        if ($value !== null && mb_strlen($value) > $definition['max']) {
            return ['errors' => ['text' => [sprintf('Zmieści się najwyżej %d znaków.', $definition['max'])]]];
        }

        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);
        $column = $definition['column'];
        /** @var string|null $before */
        $before = $order->{$column};

        if ($before === $value) {
            return ['errors' => []];
        }

        $order->{$column} = $value;
        $order->save();

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [['field' => $definition['label'], 'before' => $before, 'after' => $value]],
            'comment_changed',
        );

        return ['errors' => []];
    }

    private static function date(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
