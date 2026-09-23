<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use Carbon\Carbon;
use App\Models\AlertRule;
use App\Alerts\ConditionCatalog;
use App\Models\AlertOccurrence;
use Illuminate\Database\Eloquent\Collection;

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

        /** @var Collection<int, AlertOccurrence> $existing */
        $existing = AlertOccurrence::query()
            ->with('acknowledger')
            ->where('alert_rule_id', $rule->getKey())
            ->where('alertable_type', $alertable)
            ->whereNull('resolved_at')
            ->orderBy('id')
            ->get();

        $since = [];
        $duplicates = [];

        foreach ($existing as $occurrence) {
            $id = (int) $occurrence->alertable_id;

            // Dwa rownolegle odczyty moga otworzyc ten sam alert dwa razy
            // — tabela nie ma na to indeksu unikalnego, bo `resolved_at`
            // jest nullowalne i MariaDB i tak przepuscilaby duplikat.
            // Nadmiarowy wiersz zamykamy przy najblizszym przebiegu.
            if (isset($since[$id])) {
                $duplicates[] = (int) $occurrence->getKey();

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
                'alert_rule_id' => (int) $rule->getKey(),
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
                $gone[] = (int) $occurrence->getKey();
            }
        }

        $closing = array_merge($gone, $duplicates);

        if ($closing !== []) {
            AlertOccurrence::query()->whereIn('id', $closing)->update(['resolved_at' => $now]);
        }

        if ($fresh !== []) {
            /** @var Collection<int, AlertOccurrence> $added */
            $added = AlertOccurrence::query()
                ->where('alert_rule_id', $rule->getKey())
                ->where('alertable_type', $alertable)
                ->whereNull('resolved_at')
                ->whereIn('alertable_id', array_keys($matched))
                ->get();

            foreach ($added as $occurrence) {
                $id = (int) $occurrence->alertable_id;

                if (!isset($since[$id])) {
                    $since[$id] = $occurrence;
                }
            }
        }

        // Odhaczenie przestaje obowiazywac, gdy wartosc urosla. Bez tego
        // zlecenie odhaczone przy jednym dniu spoznienia milczaloby przy
        // trzydziestu — czyli odhaczenie kasowaloby alert, a nie uciszalo.
        $revived = [];

        foreach ($matched as $id => $value) {
            $occurrence = $since[$id] ?? null;

            if ($occurrence === null || $occurrence->acknowledged_at === null) {
                continue;
            }

            if (!$this->worsened($value, $occurrence->acknowledged_value)) {
                continue;
            }

            $revived[] = (int) $occurrence->getKey();
            $occurrence->acknowledged_at = null;
            $occurrence->acknowledged_by = null;
            $occurrence->acknowledged_value = null;
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
                'rule_id' => (int) $rule->getKey(),
                'code' => $rule->code,
                'name' => $rule->name,
                'label' => $rule->label,
                'color' => $rule->color,
                'category' => $rule->category->value,
                'module' => $rule->module,
                'alertable_type' => $alertable,
                'alertable_id' => $id,
                // Wartosc biezaca z przebiegu, nie zapisana w bazie.
                'value' => $value,
                // Bez `?->` na samej dacie: `triggered_at` nigdy nie jest
                // nullem, a PHPStan slusznie uznaje taki zapis za blad.
                'since' => $occurrence === null ? null : $occurrence->triggered_at->toDateString(),
                'occurrence_id' => $occurrence === null ? null : (int) $occurrence->getKey(),
                'acknowledged' => $occurrence?->acknowledged_at !== null,
                'acknowledged_at' => $occurrence?->acknowledged_at?->toDateString(),
                'acknowledged_by' => $this->personName($occurrence),
            ];
        }

        return $rows;
    }

    /** Kto odhaczył — imię i nazwisko, bez identyfikatora na ekranie. */
    private function personName(?AlertOccurrence $occurrence): ?string
    {
        $user = $occurrence?->acknowledger;

        if ($user === null) {
            return null;
        }

        $name = trim((string) $user->first_name . ' ' . (string) $user->last_name);

        return $name === '' ? null : $name;
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
