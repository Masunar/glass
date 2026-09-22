<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\Location;
use App\Enum\Permission;
use App\Models\Contractor;
use App\Support\PhoneSearch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

/**
 * Wyszukiwanie ogólne — jedno pole na całą aplikację.
 *
 * Stary system miał osobne wyszukiwanie na każdym ekranie, więc szukając
 * numeru trzeba było najpierw wiedzieć, gdzie on mieszka. Tutaj pytanie
 * idzie do wszystkiego naraz, a odpowiedź niesie moduł, z którego
 * pochodzi — kolor grupy to ten sam kolor, co na listwie.
 *
 * Zapytanie nigdy nie zwraca pozycji, do której pytający nie ma
 * uprawnienia: filtr jest po stronie serwera, nie po stronie widoku.
 */
final readonly class SearchService
{
    /** Poniżej dwóch znaków każde zapytanie pasuje do wszystkiego. */
    public const MIN_LENGTH = 2;

    /** Ile trafień na grupę. Lista ma prowadzić do ekranu, nie zastępować go. */
    private const PER_GROUP = 6;

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query): array
    {
        $needle = trim($query);

        if (mb_strlen($needle) < self::MIN_LENGTH) {
            return [];
        }

        $groups = [
            $this->orders($needle),
            $this->contractors($needle),
            $this->products($needle),
            $this->users($needle),
            $this->locations($needle),
        ];

        return array_values(array_filter(
            $groups,
            static fn(?array $group): bool => $group !== null && $group['hits'] !== [],
        ));
    }

    /**
     * Pola zlecenia przeszukiwane po treści, w kolejności, w jakiej
     * pokazujemy dopasowanie. Etykieta jest po to, żeby trafienie
     * mówiło, **skąd** się wzięło: „#23908 · Kowalski" nic nie tłumaczy,
     * gdy ktoś szukał słowa „balustrada".
     *
     * Bramką jest to samo uprawnienie, co dla karty zlecenia, a karta
     * pokazuje wszystkie te pola — łącznie z uwagą księgowości
     * i komentarzem dla montażysty. Szukanie w nich nie odsłania więc
     * niczego, czego uprawniony i tak by nie zobaczył.
     */
    private const ORDER_TEXT = [
        'short_note' => 'krótka uwaga',
        'production_comment' => 'komentarz do zlecenia',
        'installer_comment' => 'komentarz dla montażysty',
        'offer_comment' => 'komentarz do oferty',
        'accounting_note' => 'uwaga księgowości',
        'hold_reason' => 'powód wstrzymania',
        'cancellation_reason' => 'powód anulowania',
        'delivery_address' => 'adres dostawy',
        'delivery_contact' => 'kontakt do dostawy',
        'buyer_name' => 'nabywca',
    ];

    /** Ile znaków treści pokazać wokół dopasowania. */
    private const SNIPPET = 70;

    /**
     * Od ilu znaków szukamy w treści.
     *
     * Wyżej niż `MIN_LENGTH`, bo `LIKE '%xx%'` po dziesięciu kolumnach
     * nie skorzysta z żadnego indeksu i przy dwóch znakach przeczesuje
     * całą tabelę przy każdym naciśnięciu klawisza. Numer i kontrahent
     * zostają od dwóch znaków — tam wzorzec jest przedrostkowy albo
     * pole jest krótkie.
     */
    private const MIN_TEXT_LENGTH = 3;

    /**
     * Numer zlecenia to pierwsze, czego szuka biuro — i jedyne, co klient
     * podaje przez telefon. Dlatego ta grupa stoi na górze, a dokładne
     * trafienie w numer wyprzedza trafienie w treść.
     *
     * @return array<string, mixed>|null
     */
    private function orders(string $needle): ?array
    {
        if (!$this->allowed(Permission::ORDERS)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $needle) ?? '';

        $orders = Order::query()
            ->with(['contractor', 'status'])
            ->where(static function (Builder $query) use ($needle, $digits): void {
                if ($digits !== '') {
                    $query->orWhere('number', 'like', $digits . '%');
                }

                if (mb_strlen($needle) >= self::MIN_TEXT_LENGTH) {
                    foreach (array_keys(self::ORDER_TEXT) as $column) {
                        $query->orWhere($column, 'like', '%' . $needle . '%');
                    }
                }

                // Kontrahent jest tym, po czym biuro szuka najczesciej
                // zaraz po numerze — a telefon podaje klient, nie my.
                $query->orWhereHas(
                    'contractor',
                    static function (Builder $inner) use ($needle, $digits): void {
                        $inner
                            ->where('name', 'like', '%' . $needle . '%')
                            ->orWhere('short_name', 'like', '%' . $needle . '%');

                        if (mb_strlen($digits) >= 3) {
                            // Numer lezy w bazie tak, jak go wpisano —
                            // ze spacjami. Porownanie po samych cyfrach
                            // po obu stronach.
                            PhoneSearch::apply($inner, 'phone', $digits);
                        }
                    },
                );
            })
            // Trafienie w numer na gore: reszta i tak jest posortowana
            // od najnowszych, wiec bez tego dokladny numer potrafilby
            // zniknac pod zleceniami, ktore maja go w komentarzu.
            ->orderByRaw(
                $digits === '' ? '1' : 'CASE WHEN number = ? THEN 0 ELSE 1 END',
                $digits === '' ? [] : [(int) $digits],
            )
            ->orderByDesc('number')
            ->limit(self::PER_GROUP)
            ->get();

        $hits = [];

        foreach ($orders as $order) {
            $where = $this->matchedText($order, $needle);

            $hits[] = [
                'id' => (int) $order->getKey(),
                'title' => '#' . $order->number,
                'subtitle' => trim(implode(' · ', array_filter([
                    $order->contractor?->displayName(),
                    $order->status?->name,
                    $where,
                ]))),
                'path' => '/orders/' . $order->getKey(),
            ];
        }

        return $this->group('orders', 'Zlecenia', 'zlec', $hits);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function contractors(string $needle): ?array
    {
        if (!$this->allowed(Permission::CONTRACTORS)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $needle) ?? '';

        $contractors = Contractor::query()
            ->where(static function (Builder $query) use ($needle, $digits): void {
                $query
                    ->where('name', 'like', '%' . $needle . '%')
                    ->orWhere('short_name', 'like', '%' . $needle . '%')
                    ->orWhere('email', 'like', '%' . $needle . '%');

                // NIP i telefon wpisuje sie ze spacjami albo bez, wiec
                // szukamy po samych cyfrach.
                if (mb_strlen($digits) >= 3) {
                    $query->orWhere('tax_id', 'like', $digits . '%');

                    PhoneSearch::apply($query, 'phone', $digits);
                }
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get();

        $hits = [];

        foreach ($contractors as $contractor) {
            $hits[] = [
                'id' => (int) $contractor->getKey(),
                'title' => $contractor->displayName(),
                'subtitle' => trim(implode(' · ', array_filter([
                    $contractor->tax_id === null ? null : 'NIP ' . $contractor->tax_id,
                    $contractor->phone,
                    $contractor->is_active ? null : 'wyłączony',
                ]))),
                'path' => '/contractors',
            ];
        }

        return $this->group('contractors', 'Kontrahenci', 'zlec', $hits);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function products(string $needle): ?array
    {
        if (!$this->allowed(Permission::PRICE_LIST)) {
            return null;
        }

        $products = Product::query()
            ->with('group')
            ->where(static function (Builder $query) use ($needle): void {
                $query
                    ->where('name', 'like', '%' . $needle . '%')
                    ->orWhere('code', 'like', '%' . $needle . '%')
                    ->orWhere('manufacturer_code', 'like', '%' . $needle . '%');
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get();

        $hits = [];

        foreach ($products as $product) {
            $hits[] = [
                'id' => (int) $product->getKey(),
                'title' => $product->name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $product->getAttribute('group')?->name,
                    $product->code,
                ]))),
                'path' => '/price-list?section=' . $product->section->value,
            ];
        }

        return $this->group('products', 'Cennik', 'zlec', $hits);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function users(string $needle): ?array
    {
        if (!$this->allowed(Permission::USERS)) {
            return null;
        }

        $users = User::query()
            ->with('roles')
            ->where(static function (Builder $query) use ($needle): void {
                $query
                    ->where('first_name', 'like', '%' . $needle . '%')
                    ->orWhere('last_name', 'like', '%' . $needle . '%')
                    ->orWhere('email', 'like', '%' . $needle . '%');
            })
            ->orderBy('first_name')
            ->limit(self::PER_GROUP)
            ->get();

        $hits = [];

        foreach ($users as $user) {
            $hits[] = [
                'id' => (int) $user->getKey(),
                'title' => trim($user->first_name . ' ' . ($user->last_name ?? '')),
                'subtitle' => trim(implode(' · ', array_filter([
                    $user->email,
                    $user->roles->pluck('name')->implode(', ') ?: null,
                ]))),
                'path' => '/users',
            ];
        }

        return $this->group('users', 'Użytkownicy', 'adm', $hits);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function locations(string $needle): ?array
    {
        if (!$this->allowed(Permission::DICTIONARIES) && !$this->allowed(Permission::LOCATIONS)) {
            return null;
        }

        $locations = Location::query()
            ->where('name', 'like', '%' . $needle . '%')
            ->orderBy('position')
            ->limit(self::PER_GROUP)
            ->get();

        $hits = [];

        foreach ($locations as $location) {
            $hits[] = [
                'id' => (int) $location->getKey(),
                'title' => $location->name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $location->address_city,
                    $location->is_production ? 'produkcja' : null,
                    $location->is_pickup_point ? 'odbiór' : null,
                ]))),
                'path' => '/dictionaries',
            ];
        }

        return $this->group('locations', 'Lokalizacje', 'adm', $hits);
    }

    /**
     * Które pole zlecenia pasowało i co w nim stoi.
     *
     * `null`, gdy dopasowanie poszło przez numer albo kontrahenta —
     * wtedy widać je już w tytule i podtytule, a powtarzanie tego
     * trzeci raz zabiera miejsce.
     */
    private function matchedText(Order $order, string $needle): ?string
    {
        foreach (self::ORDER_TEXT as $column => $label) {
            /** @var string|null $value */
            $value = $order->{$column};

            if ($value === null || $value === '') {
                continue;
            }

            $at = mb_stripos($value, $needle);

            if ($at === false) {
                continue;
            }

            return $label . ': ' . $this->snippet($value, $at);
        }

        return null;
    }

    /**
     * Wycinek wokół dopasowania.
     *
     * Ucinamy na granicy słowa, bo urwane w połowie wyrazu czyta się
     * gorzej niż o kilka znaków dłuższe.
     */
    private function snippet(string $value, int $at): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if (mb_strlen($value) <= self::SNIPPET) {
            return $value;
        }

        $start = max(0, $at - (int) (self::SNIPPET / 3));
        $cut = mb_substr($value, $start, self::SNIPPET);

        if ($start > 0) {
            $space = mb_strpos($cut, ' ');
            $cut = $space === false ? $cut : mb_substr($cut, $space + 1);
            $cut = '…' . $cut;
        }

        return $cut . '…';
    }

    /**
     * @param list<array<string, mixed>> $hits
     * @return array<string, mixed>
     */
    private function group(string $key, string $label, string $module, array $hits): array
    {
        return ['key' => $key, 'label' => $label, 'module' => $module, 'hits' => $hits];
    }

    private function allowed(Permission $permission): bool
    {
        $user = Auth::user();

        return $user !== null && $user->can($permission->value . '.list');
    }
}
