<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use Carbon\Carbon;
use App\Models\Order;
use App\Services\Orders\OrderOwnerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Odczyt alertów: znaczniki w wierszach, liczniki przy zakładkach,
 * pasmo na pulpicie.
 *
 * Alert dotyczy zlecenia, więc **widzi go ten, kto widzi zlecenie** —
 * nie osobne uprawnienie. Uprawnienie `alerts` pilnuje wyłącznie ekranu
 * reguł, bo tam zmienia się konfiguracja, a nie ogląda dane.
 *
 * **Odhaczony alert znika z liczników, ale zostaje w wierszu.** Licznik
 * odpowiada na pytanie „ile wymaga reakcji", a znacznik przy zleceniu na
 * „co z nim jest" — to dwa różne pytania. Gdyby odhaczenie zdejmowało
 * znacznik, sprawa znikałaby z oczu zamiast przestać krzyczeć.
 *
 * **Adresat alertu nie jest zapisany — jest wyprowadzany z rzeczy,
 * której alert dotyczy.** Alert o zleceniu należy do jego prowadzącego.
 * Odbiorca zapisany przy wystąpieniu byłby drugim źródłem tej samej
 * prawdy: rozjechałby się przy pierwszym przekazaniu zlecenia i wisiał
 * przy poprzedniej osobie bez żadnego objawu. Rzeczy bez właściciela —
 * produkt na magazynie, partia w piecu — zostają wspólne i nie udają,
 * że są czyjeś.
 */
final class AlertBoard
{
    /**
     * Prowadzący zleceń objętych alertami, jeden raz na dzień przebiegu.
     *
     * Pasmo pulpitu pyta o podpisy osobno dla każdej reguły — przy ośmiu
     * regułach byłoby to osiem takich samych zapytań o tę samą garść
     * zleceń. Ten sam powód, dla którego przebieg silnika jest
     * zapamiętywany.
     *
     * @var array<string, array<int, array{user_id: int, name: string, initials: string}>>
     */
    private array $ownerMemo = [];

    public function __construct(
        private readonly AlertEngine $engine = new AlertEngine(),
    ) {
    }

    /**
     * Alerty zleceń w postaci gotowej dla listy: id zlecenia => znaczniki.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function forOrders(?Carbon $day = null): array
    {
        $byOrder = [];

        foreach ($this->engine->run($day) as $row) {
            if ($row['alertable_type'] !== Order::class) {
                continue;
            }

            $id = (int) $row['alertable_id'];

            $byOrder[$id][] = [
                'code' => $row['code'],
                'label' => $row['label'],
                'color' => $row['color'],
                'category' => $row['category'],
                'value' => $row['value'],
                'since' => $row['since'],
                'occurrence_id' => $row['occurrence_id'],
                'acknowledged' => $row['acknowledged'],
                'acknowledged_at' => $row['acknowledged_at'],
                'acknowledged_by' => $row['acknowledged_by'],
            ];
        }

        return $byOrder;
    }

    /**
     * Czerwone liczniki przy zakładkach statusów.
     *
     * Kluczem jest kod statusu, a `null` to zakładka „Wszystkie”. Licznik
     * liczy **zlecenia, nie alerty**: zlecenie z trzema problemami to
     * jedna sprawa do ruszenia, nie trzy.
     *
     * `$ownerId` zawęża licznik do zleceń jednej osoby — lista z filtrem
     * „moje" nad czerwoną liczbą liczoną z całości pokazywałaby problemy,
     * których w widocznych wierszach nie ma.
     *
     * @return array<string, int> kod statusu => liczba zleceń, plus klucz '' dla całości
     */
    public function orderCounts(?Carbon $day = null, ?int $ownerId = null): array
    {
        $ids = [];

        foreach ($this->forOrders($day) as $id => $marks) {
            foreach ($marks as $mark) {
                if ($mark['acknowledged'] !== true) {
                    $ids[] = $id;

                    break;
                }
            }
        }

        if ($ids === []) {
            return ['' => 0];
        }

        /** @var Collection<int, Order> $orders */
        $orders = Order::query()
            ->with('status')
            ->whereIn('id', $ids)
            ->when(
                $ownerId !== null,
                static fn(Builder $builder): Builder => $builder->where('owner_id', $ownerId),
            )
            ->get();

        $counts = ['' => $orders->count()];

        foreach ($orders as $order) {
            $code = $order->status?->code;

            if ($code === null) {
                continue;
            }

            $counts[$code] = ($counts[$code] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Pasmo alertów na pulpicie: reguła, ile razy zapalona, dokąd idzie.
     *
     * **Reguły z moimi sprawami idą pierwsze.** Kolejność jest jedynym
     * adresowaniem, jakie tu stosujemy: żadna reguła nie znika i nie
     * dostaje osobnego licznika „ile z nich moich" — ten liczyłby ten
     * sam zbiór drugi raz, obok czerwonych liczb przy zakładkach.
     *
     * @return list<array<string, mixed>>
     */
    public function summary(?Carbon $day = null, ?int $userId = null): array
    {
        /** @var array<string, int> $counts */
        $counts = [];
        /** @var array<string, array<string, mixed>> $meta */
        $meta = [];

        foreach ($this->engine->run($day) as $row) {
            if ($row['acknowledged'] === true) {
                continue;
            }

            $code = (string) $row['code'];

            $counts[$code] = ($counts[$code] ?? 0) + 1;
            $meta[$code] ??= [
                'code' => $code,
                'name' => $row['name'],
                'label' => $row['label'],
                'color' => $row['color'],
                'category' => $row['category'],
                'module' => $row['module'],
                'resource' => $row['resource'],
            ];
        }

        $mine = $this->mineByCode($day, $userId);

        // Dwa wiadra zamiast sortowania: reguły bez moich spraw
        // zachowują kolejność z panelu, a przy sortowaniu po kluczu
        // trzeba by pilnować, żeby remis nie zaczął porównywać samych
        // wierszy.
        $first = [];
        $rest = [];

        foreach ($meta as $code => $row) {
            $row['count'] = $counts[$code] ?? 0;

            if ($mine[$code] ?? false) {
                $first[] = $row;

                continue;
            }

            $rest[] = $row;
        }

        return [...$first, ...$rest];
    }

    /**
     * Które reguły mają choć jedną moją sprawę.
     *
     * @return array<string, bool>
     */
    private function mineByCode(?Carbon $day, ?int $userId): array
    {
        if ($userId === null) {
            return [];
        }

        $owners = $this->owners($day);
        $mine = [];

        foreach ($this->engine->run($day) as $row) {
            if ($row['acknowledged'] === true) {
                continue;
            }

            $owner = $this->ownerOf($row, $owners);

            if ($owner !== null && $owner['user_id'] === $userId) {
                $mine[(string) $row['code']] = true;
            }
        }

        return $mine;
    }

    /**
     * Rzeczy objęte regułą — podpis i dokąd prowadzi.
     *
     * Nie „numery zleceń": reguła może dotyczyć produktu albo partii
     * w piecu, a pasmo alertów ma je nazwać tak samo. Podpis składa
     * warunek, bo tam już stoi zapytanie o tę tabelę.
     *
     * **Moje sprawy idą pierwsze, cudze dostają podpis prowadzącego.**
     * Kolejność ustala się **przed** przycięciem do `$limit` — inaczej
     * moje zlecenie na szóstej pozycji zniknęłoby, zanim cokolwiek
     * zdążyłoby je przesunąć, i nikt by się o tym nie dowiedział.
     *
     * @return list<array{label: string, path: string|null, value: string|null,
     *     owner: string|null, owner_initials: string|null, is_mine: bool}>
     */
    public function subjectsFor(
        string $code,
        int $limit = 5,
        ?Carbon $day = null,
        ?int $userId = null,
    ): array {
        if ($limit < 1) {
            return [];
        }

        $owners = $this->owners($day);
        $first = [];
        $rest = [];

        foreach ($this->engine->run($day) as $row) {
            if ($row['code'] !== $code || $row['acknowledged'] === true) {
                continue;
            }

            $owner = $this->ownerOf($row, $owners);
            $isMine = $userId !== null && $owner !== null && $owner['user_id'] === $userId;

            $candidate = [
                'id' => (int) $row['alertable_id'],
                'value' => $row['value'],
                // Pelne imie idzie razem z inicjalami: dwie osoby moga
                // miec te same, a podpowiedz pod kursorem jest jedynym
                // miejscem, gdzie da sie to rozstrzygnac.
                'owner' => $owner['name'] ?? null,
                'owner_initials' => $owner['initials'] ?? null,
                'is_mine' => $isMine,
            ];

            if ($isMine) {
                $first[] = $candidate;

                continue;
            }

            $rest[] = $candidate;
        }

        // Podpisy dopiero dla tych, ktore trafia na ekran — porcjami po
        // `$limit`. Rzecz bez podpisu (skasowana miedzy przebiegiem
        // a odczytem) jest pomijana, a jej miejsce zajmuje nastepna,
        // tak jak wtedy, gdy podpisy liczyl silnik dla wszystkich.
        $subjects = [];

        foreach (array_chunk([...$first, ...$rest], $limit) as $chunk) {
            $labels = $this->engine->subjects($code, array_column($chunk, 'id'));

            foreach ($chunk as $candidate) {
                $label = $labels[$candidate['id']] ?? null;

                if ($label === null) {
                    continue;
                }

                $subjects[] = [
                    'label' => $label['label'],
                    'path' => $label['path'],
                    'value' => $candidate['value'],
                    'owner' => $candidate['owner'],
                    'owner_initials' => $candidate['owner_initials'],
                    'is_mine' => $candidate['is_mine'],
                ];

                if (count($subjects) === $limit) {
                    return $subjects;
                }
            }
        }

        return $subjects;
    }

    /**
     * Prowadzący zleceń objętych dzisiejszymi alertami.
     *
     * Jedno zapytanie na przebieg, nie jedno na regułę.
     *
     * @return array<int, array{user_id: int, name: string, initials: string}>
     */
    private function owners(?Carbon $day): array
    {
        $key = ($day ?? Carbon::today())->startOfDay()->toDateString();

        if (isset($this->ownerMemo[$key])) {
            return $this->ownerMemo[$key];
        }

        $ids = [];

        foreach ($this->engine->run($day) as $row) {
            if ($row['alertable_type'] === Order::class) {
                $ids[(int) $row['alertable_id']] = true;
            }
        }

        $map = [];

        if ($ids !== []) {
            /** @var Collection<int, Order> $orders */
            $orders = Order::query()
                ->with('owner')
                ->whereIn('id', array_keys($ids))
                ->get();

            foreach ($orders as $order) {
                $owner = $order->owner;

                // Zlecenie bez prowadzacego jest mozliwe tylko po
                // skasowaniu konta — wtedy nie ma kogo podpisac i alert
                // zostaje wspolny, tak jak magazynowy.
                if ($owner === null) {
                    continue;
                }

                $map[(int) $order->getKey()] = [
                    'user_id' => (int) $owner->getKey(),
                    'name' => OrderOwnerService::name($owner),
                    'initials' => (string) OrderOwnerService::initials($owner),
                ];
            }
        }

        $this->ownerMemo[$key] = $map;

        return $map;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, array{user_id: int, name: string, initials: string}> $owners
     * @return array{user_id: int, name: string, initials: string}|null
     */
    private function ownerOf(array $row, array $owners): ?array
    {
        // Tylko zlecenie ma prowadzacego. Produkt i partia w piecu nie
        // naleza do nikogo i udawanie, ze naleza, byloby wymyslaniem
        // danych.
        if ($row['alertable_type'] !== Order::class) {
            return null;
        }

        return $owners[(int) $row['alertable_id']] ?? null;
    }
}
