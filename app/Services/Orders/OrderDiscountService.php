<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enum\Section;
use App\Models\Role;
use App\Models\User;
use App\Models\Order;
use App\Models\PriceSection;
use App\Models\OrderDiscount;
use App\Services\AuditTrail;
use App\Models\RoleDiscountLimit;
use Illuminate\Support\Facades\Auth;

/**
 * Rabat na zleceniu — czwarty poziom ceny.
 *
 * Nadawany per sekcja asortymentu, bo tak działa cennik: kontrahent ma
 * osobną sekcję cenową dla szkła, okuć i usług, więc rabat też musi być
 * osobny. Jeden rabat „na zlecenie" kasowałby tę strukturę.
 *
 * **Limit jest danymi, nie kodem.** `role_discount_limits` trzyma wiersz
 * rola × sekcja cenowa; handlowiec dostaje najwyższy limit ze swoich ról.
 * Stary system miał trzy kolumny dla trzech ról, więc czwarta rola
 * sprzedażowa wymagałaby zmiany schematu.
 *
 * **Brak wiersza limitu oznacza brak prawa do rabatu, nie brak
 * ograniczenia.** Rola, której nikt nie przypisał limitu, nie może
 * obniżyć ceny — odwrotna interpretacja pozwalałaby nowemu użytkownikowi
 * dać sto procent, zanim ktokolwiek zauważy.
 */
final readonly class OrderDiscountService
{
    public function __construct(
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * Rabaty zlecenia razem z limitem bieżącego użytkownika — ekran ma
     * pokazać, ile wolno, zanim ktoś wpisze za dużo.
     *
     * @return list<array<string, mixed>>
     */
    public function board(Order $order): array
    {
        $current = [];

        foreach ($order->discounts as $discount) {
            $current[$discount->section->value] = $discount;
        }

        $limits = $this->limits($order);
        $rows = [];

        foreach (Section::cases() as $section) {
            $discount = $current[$section->value] ?? null;
            $limit = $limits[$section->value];

            $rows[] = [
                'section' => $section->value,
                'percent' => $discount === null ? '0.00' : (string) $discount->percent,
                'max_percent' => number_format($limit['max'], 2, '.', ''),
                'price_section' => $limit['price_section'],
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $input rabaty kluczowane wartością sekcji
     * @return array{errors: array<string, list<string>>}
     */
    public function save(int $orderId, array $input): array
    {
        /** @var Order $order */
        $order = Order::query()
            ->with(['contractor.priceSections.priceSection', 'discounts'])
            ->findOrFail($orderId);

        $limits = $this->limits($order);
        $errors = [];
        $wanted = [];

        foreach (Section::cases() as $section) {
            if (!array_key_exists($section->value, $input)) {
                continue;
            }

            $raw = $input[$section->value];
            $value = $raw === '' || $raw === null ? 0.0 : (float) $raw;

            if (!is_numeric($raw) && $raw !== '' && $raw !== null) {
                $errors[$section->value] = ['Rabat musi być liczbą.'];
                continue;
            }

            // Zwyzka jako rabat ujemny nie jest rozstrzygnieta w analizie
            // (Z-15) — do czasu decyzji nie zgadujemy, co mialaby znaczyc.
            if ($value < 0) {
                $errors[$section->value] = ['Zwyżka nie jest jeszcze obsługiwana — podaj rabat od zera w górę.'];
                continue;
            }

            $max = $limits[$section->value]['max'];

            if ($value > $max) {
                $errors[$section->value] = [sprintf(
                    'Twoja rola pozwala tu na najwyżej %s %% (sekcja cenowa „%s”).',
                    rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.'),
                    $limits[$section->value]['price_section'] ?? '—',
                )];
                continue;
            }

            $wanted[$section->value] = $value;
        }

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $changes = [];

        foreach ($wanted as $sectionValue => $value) {
            /** @var OrderDiscount|null $existing */
            $existing = $order->discounts
                ->first(static fn(OrderDiscount $row): bool => $row->section->value === $sectionValue);

            $before = $existing === null ? '0.00' : (string) $existing->percent;
            $after = number_format($value, 2, '.', '');

            if ($before === $after) {
                continue;
            }

            $changes[] = ['field' => 'rabat ' . $sectionValue, 'before' => $before, 'after' => $after];

            // Rabat zerowy to brak rabatu, nie rabat o wartosci zero —
            // wiersz znika, zeby podsumowanie nie pokazywalo pustej pozycji.
            if ($value === 0.0) {
                $existing?->delete();
                continue;
            }

            OrderDiscount::query()->updateOrCreate(
                ['order_id' => (int) $order->getKey(), 'section' => $sectionValue],
                ['percent' => $after],
            );
        }

        if ($changes !== []) {
            $this->audit->write(Order::class, (int) $order->getKey(), $changes, 'discount_changed');
        }

        return ['errors' => []];
    }

    /**
     * Maksymalny rabat bieżącego użytkownika w każdej sekcji asortymentu.
     *
     * @return array<string, array{max: float, price_section: string|null}>
     */
    private function limits(Order $order): array
    {
        $roleIds = $this->roleIds();
        $limits = [];

        foreach (Section::cases() as $section) {
            $priceSection = $order->contractor?->priceSectionFor($section)
                ?? $this->defaultPriceSection($section);

            $max = 0.0;

            if ($priceSection !== null && $roleIds !== []) {
                $max = (float) RoleDiscountLimit::query()
                    ->where('price_section_id', $priceSection->getKey())
                    ->whereIn('role_id', $roleIds)
                    ->max('max_discount_percent');
            }

            $limits[$section->value] = [
                'max' => $max,
                'price_section' => $priceSection?->name,
            ];
        }

        return $limits;
    }

    /**
     * @return list<int>
     */
    private function roleIds(): array
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            return [];
        }

        $ids = [];

        /** @var Role $role */
        foreach ($user->roles as $role) {
            $ids[] = (int) $role->getKey();
        }

        return $ids;
    }

    private function defaultPriceSection(Section $section): ?PriceSection
    {
        /** @var PriceSection|null */
        return PriceSection::query()
            ->where('section', $section->value)
            ->where('is_default', true)
            ->first();
    }
}
