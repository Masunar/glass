<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use Carbon\Carbon;
use App\Models\AlertRule;
use App\Alerts\ConditionCatalog;
use App\Models\AlertOccurrence;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Silnik alertów — przebieg reguł i uzgodnienie wystąpień.
 *
 * **Alert liczy się wtedy, gdy ktoś patrzy.** W `compose.yml` nie ma
 * usługi harmonogramu, więc reguła licząca „dni po terminie" w wariancie
 * wsadowym byłaby mechanizmem napisanym i nigdy nieuruchomionym — a taki
 * wygląda jak działający. Czas mija bez czyjegokolwiek zapisu, więc
 * przebieg musi wisieć na odczycie.
 *
 * **Wystąpienia są uzgadniane, nie dopisywane** — jak `OrderStock`:
 * warunek spełniony po raz pierwszy otwiera wiersz, warunek, który
 * przestał być spełniony, zamyka istniejący. Dzięki temu tabela niesie
 * „od kiedy", a nie dziennik przebiegów.
 *
 * **Odhaczenie („wiem o tym") nie zamyka wystąpienia.** `resolved_at`
 * znaczy „warunek przestał być spełniony"; odhaczenie to decyzja
 * człowieka o czymś, co nadal trwa. Alert odhaczony milknie w licznikach,
 * ale **wraca sam, gdy zrobi się gorzej** — dlatego odhaczenie zapamiętuje
 * wartość, przy której padło, i silnik porównuje ją z bieżącą.
 *
 * **`value` w bazie to wartość z chwili otwarcia i nie jest odświeżana.**
 * „3 dni po terminie" zmienia się jutro w „4 dni po" — gdyby silnik
 * przepisywał tę liczbę, każdy odczyt byłby zapisem. Ekran pokazuje
 * wartość bieżącą (z przebiegu), baza trzyma pierwszą.
 */
final class AlertEngine
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $memo = [];

    /**
     * Warunki reguł z ostatniego przebiegu, po kodzie reguły — dla
     * podpisów liczonych dopiero wtedy, gdy ekran o nie poprosi.
     *
     * @var array<string, \App\Alerts\AlertCondition>
     */
    private array $conditions = [];

    public function __construct(
        private readonly ConditionCatalog $catalog = new ConditionCatalog(),
    ) {
    }

    /**
     * Otwarte alerty na dziś, po uzgodnieniu z bazą.
     *
     * @return list<array<string, mixed>>
     */
    public function run(?Carbon $day = null): array
    {
        $on = ($day ?? Carbon::today())->startOfDay();
        $key = $on->toDateString();

        // Jeden przebieg na zadanie. Lista zlecen pyta o liczniki
        // zakladek i o znaczniki w wierszach — to jedno pytanie, nie dwa.
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        /** @var Collection<int, AlertRule> $rules */
        $rules = AlertRule::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $open = [];

        foreach ($rules as $rule) {
            foreach ($this->reconcile($rule, $on) as $row) {
                $open[] = $row;
            }
        }

        $this->memo[$key] = $open;

        return $open;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reconcile(AlertRule $rule, Carbon $day): array
    {
        /** @var array<string, mixed> $params */
        $params = $rule->condition;
        $type = is_string($params['type'] ?? null) ? $params['type'] : $rule->code;
        $condition = $this->catalog->find($type);

        // Regula wskazujaca typ, ktorego nie ma w katalogu, nie jest
        // regula pusta — jest regula zepsuta. Milczace pominiecie
        // zostawiloby ja na ekranie jako aktywna.
        if ($condition === null) {
            throw new \RuntimeException(
                'Reguła alertu "' . $rule->code . '" wskazuje nieznany typ warunku: ' . $type . '.',
            );
        }

        $matched = $condition->find($day, $params);
        $alertable = $condition->alertable();

        // Warunek zapamietany dla podpisow: pasmo pulpitu prosi o nie
        // pozniej, dla kilku pokazywanych wierszy, a nie dla wszystkich.
        $this->conditions[(string) $rule->code] = $condition;

        $ruleId = (int) $rule->getKey();
        $since = [];
        $duplicates = [];

        foreach ($this->openOccurrences($ruleId, $alertable) as $occurrence) {
            $id = $occurrence['alertable_id'];

            // Dwa rownolegle odczyty moga otworzyc ten sam alert dwa razy
            // — tabela nie ma na to indeksu unikalnego, bo `resolved_at`
            // jest nullowalne i MariaDB i tak przepuscilaby duplikat.
            // Nadmiarowy wiersz zamykamy przy najblizszym przebiegu.
            if (isset($since[$id])) {
                $duplicates[] = $occurrence['id'];

                continue;
            }

            $since[$id] = $occurrence;
        }

        $now = Carbon::now();
        $fresh = [];

        foreach ($matched as $id => $value) {
            if (isset($since[$id])) {
                continue;
            }

            $fresh[] = [
                'alert_rule_id' => $ruleId,
                'alertable_type' => $alertable,
                'alertable_id' => $id,
                'value' => $value,
                'triggered_at' => $now,
            ];
        }

        if ($fresh !== []) {
            AlertOccurrence::query()->insert($fresh);
        }

        $gone = [];

        foreach ($since as $id => $occurrence) {
            if (!array_key_exists($id, $matched)) {
                $gone[] = $occurrence['id'];
            }
        }

        $closing = array_merge($gone, $duplicates);

        if ($closing !== []) {
            AlertOccurrence::query()->whereIn('id', $closing)->update(['resolved_at' => $now]);
        }

        if ($fresh !== []) {
            $ids = array_map(
                static fn(array $row): int => (int) $row['alertable_id'],
                $fresh,
            );

            foreach ($this->openOccurrences($ruleId, $alertable, $ids) as $occurrence) {
                $since[$occurrence['alertable_id']] ??= $occurrence;
            }
        }

        // Odhaczenie przestaje obowiazywac, gdy wartosc urosla. Bez tego
        // zlecenie odhaczone przy jednym dniu spoznienia milczaloby przy
        // trzydziestu — czyli odhaczenie kasowaloby alert, a nie uciszalo.
        $revived = [];

        foreach ($matched as $id => $value) {
            $occurrence = $since[$id] ?? null;

            if ($occurrence === null || $occurrence['acknowledged_at'] === null) {
                continue;
            }

            if (!$this->worsened($value, $occurrence['acknowledged_value'])) {
                continue;
            }

            $revived[] = $occurrence['id'];
            $since[$id]['acknowledged_at'] = null;
            $since[$id]['acknowledged_by'] = null;
            $since[$id]['acknowledged_value'] = null;
        }

        if ($revived !== []) {
            AlertOccurrence::query()->whereIn('id', $revived)->update([
                'acknowledged_at' => null,
                'acknowledged_by' => null,
                'acknowledged_value' => null,
            ]);
        }

        $rows = [];

        foreach ($matched as $id => $value) {
            $occurrence = $since[$id] ?? null;

            $rows[] = [
                'rule_id' => $ruleId,
                'code' => $rule->code,
                'name' => $rule->name,
                'label' => $rule->label,
                'color' => $rule->color,
                'category' => $rule->category->value,
                'module' => $rule->module,
                // Zasob z warunku, nie z reguly: administrator moze
                // przestawic modul na ekranie, ale nie zmienia tym
                // tego, czego alert dotyczy.
                'resource' => $condition->resource(),
                'alertable_type' => $alertable,
                'alertable_id' => $id,
                // Wartosc biezaca z przebiegu, nie zapisana w bazie.
                'value' => $value,
                'since' => $occurrence['since'] ?? null,
                'occurrence_id' => $occurrence['id'] ?? null,
                'acknowledged' => ($occurrence['acknowledged_at'] ?? null) !== null,
                'acknowledged_at' => $occurrence['acknowledged_at'] ?? null,
                'acknowledged_by' => $occurrence['acknowledged_by'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Podpisy rzeczy objętych regułą — tylko dla podanych identyfikatorów.
     *
     * Silnik liczył podpis dla każdego zapalonego alertu, a pasmo pulpitu
     * pokazuje pięć na regułę. Przy 1700 zleceniach po terminie to 1700
     * nazw kontrahentów, z których ekran brał pięć. Podpis składa nadal
     * warunek — ten sam, który alert zapalił, zapamiętany w przebiegu.
     *
     * @param list<int> $ids
     * @return array<int, array{label: string, path: string|null}>
     */
    public function subjects(string $code, array $ids): array
    {
        $condition = $this->conditions[$code] ?? null;

        if ($condition === null || $ids === []) {
            return [];
        }

        return $condition->subjects($ids);
    }

    /**
     * Otwarte wystąpienia reguły — same kolumny, bez modeli.
     *
     * Przy kilku tysiącach otwartych alertów budowanie modelu z relacją
     * odhaczającego dla każdego wiersza było jedną piątą przebiegu,
     * a z modelu brane były cztery pola. Imię odhaczającego dochodzi
     * złączeniem w tym samym zapytaniu.
     *
     * Daty ucinane do dnia wprost z kolumny: ekran pokazuje „od kiedy"
     * z dokładnością do dnia, a parsowanie każdej daty do obiektu
     * po to, żeby ją z powrotem sformatować, kosztuje tyle samo co odczyt.
     *
     * @param list<int>|null $ids
     * @return list<array{id: int, alertable_id: int, since: string, acknowledged_at: string|null,
     *     acknowledged_value: string|null, acknowledged_by: string|null}>
     */
    private function openOccurrences(int $ruleId, string $alertable, ?array $ids = null): array
    {
        $rows = AlertOccurrence::query()
            ->toBase()
            ->leftJoin('users', 'users.id', '=', 'alert_occurrences.acknowledged_by')
            ->where('alert_occurrences.alert_rule_id', $ruleId)
            ->where('alert_occurrences.alertable_type', $alertable)
            ->whereNull('alert_occurrences.resolved_at')
            ->when(
                $ids !== null,
                static fn(QueryBuilder $query): QueryBuilder => $query->whereIn(
                    'alert_occurrences.alertable_id',
                    $ids ?? [],
                ),
            )
            ->orderBy('alert_occurrences.id')
            ->get([
                'alert_occurrences.id',
                'alert_occurrences.alertable_id',
                'alert_occurrences.triggered_at',
                'alert_occurrences.acknowledged_at',
                'alert_occurrences.acknowledged_value',
                'users.first_name',
                'users.last_name',
            ]);

        $out = [];

        foreach ($rows as $row) {
            $name = trim((string) $row->first_name . ' ' . (string) $row->last_name);

            $out[] = [
                'id' => (int) $row->id,
                'alertable_id' => (int) $row->alertable_id,
                'since' => substr((string) $row->triggered_at, 0, 10),
                'acknowledged_at' => $row->acknowledged_at === null ? null : substr((string) $row->acknowledged_at, 0, 10),
                'acknowledged_value' => $row->acknowledged_value === null ? null : (string) $row->acknowledged_value,
                // Odhaczajacy moze miec skasowane konto — wtedy wiadomo,
                // ze odhaczono, ale nie ma kogo podpisac.
                'acknowledged_by' => $name === '' ? null : $name,
            ];
        }

        return $out;
    }

    /**
     * Czy wartość urosła od chwili odhaczenia.
     *
     * **Brak liczby nie jest pogorszeniem.** Warunki bez wartości —
     * „brak rysunków", „wstrzymane", „reklamacja" — nie mają czego
     * porównać, więc odhaczenie przy nich trzyma, dopóki warunek trwa.
     * Zgadywanie, że „coś się zmieniło", byłoby wymyślaniem liczby,
     * której warunek nigdy nie zwrócił.
     */
    private function worsened(?string $current, ?string $acknowledged): bool
    {
        if ($current === null || $acknowledged === null) {
            return false;
        }

        if (!is_numeric($current) || !is_numeric($acknowledged)) {
            return false;
        }

        return (float) $current > (float) $acknowledged;
    }

    /**
     * Zamyka wszystkie otwarte wystąpienia reguły — po jej wyłączeniu
     * albo usunięciu. Bez tego wyłączona reguła zostawiłaby na ekranie
     * alerty, których nic już nie przelicza.
     */
    public function close(AlertRule $rule): void
    {
        AlertOccurrence::query()
            ->where('alert_rule_id', $rule->getKey())
            ->whereNull('resolved_at')
            ->update(['resolved_at' => Carbon::now()]);

        $this->memo = [];
    }

    public function forget(): void
    {
        $this->memo = [];
    }
}
