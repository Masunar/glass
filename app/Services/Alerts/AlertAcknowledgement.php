<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use Carbon\Carbon;
use App\Models\AlertOccurrence;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\Auth;

/**
 * Odhaczenie alertu — „wiem o tym".
 *
 * **Odhaczenie nie zamyka wystąpienia.** `resolved_at` znaczy „warunek
 * przestał być spełniony" i ustawia je silnik; odhaczenie to decyzja
 * człowieka o czymś, co nadal trwa. Zlecenie po terminie po odhaczeniu
 * dalej jest po terminie — przestaje tylko liczyć się do czerwonych
 * liczników.
 *
 * **Zapamiętujemy wartość z chwili odhaczenia.** Bez niej nie da się
 * odpowiedzieć na pytanie „czy zrobiło się gorzej", a alert odhaczony
 * przy jednym dniu spóźnienia milczałby przy trzydziestu. Porównanie
 * robi silnik przy najbliższym przebiegu.
 *
 * **Odhaczenie jest wspólne dla wszystkich.** Zlecenie jest jedno,
 * a „dzwoniłem do klienta" to informacja dla biura, nie prywatna
 * notatka. Kto odhaczył, zostaje zapisane — i widać to na ekranie.
 */
final readonly class AlertAcknowledgement
{
    public function __construct(
        private AlertEngine $engine = new AlertEngine(),
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * @return array{errors: array<string, list<string>>}
     */
    public function acknowledge(int $occurrenceId, ?Carbon $day = null): array
    {
        $occurrence = $this->open($occurrenceId);

        if ($occurrence === null) {
            return ['errors' => ['alert' => ['Tego alertu już nie ma — warunek przestał być spełniony.']]];
        }

        if ($occurrence->acknowledged_at !== null) {
            return ['errors' => ['alert' => ['Ten alert jest już odhaczony.']]];
        }

        $occurrence->update([
            'acknowledged_at' => Carbon::now(),
            'acknowledged_by' => Auth::id(),
            // Wartosc biezaca, nie ta zapisana przy otwarciu: odhaczam
            // to, co widze dzisiaj, a nie to, co bylo tydzien temu.
            'acknowledged_value' => $this->current($occurrence, $day),
        ]);

        $this->record($occurrence, 'alert_acknowledged', null, $occurrence->acknowledged_value);
        $this->engine->forget();

        return ['errors' => []];
    }

    /**
     * Cofnięcie odhaczenia.
     *
     * Odhaczenie kliknięte przez pomyłkę jest gorsze niż jego brak:
     * sprawa milknie, a nikt o tym nie wie. Cofnięcie musi być tak samo
     * tanie jak odhaczenie.
     *
     * @return array{errors: array<string, list<string>>}
     */
    public function revoke(int $occurrenceId): array
    {
        $occurrence = $this->open($occurrenceId);

        if ($occurrence === null) {
            return ['errors' => ['alert' => ['Tego alertu już nie ma.']]];
        }

        if ($occurrence->acknowledged_at === null) {
            return ['errors' => ['alert' => ['Ten alert nie jest odhaczony.']]];
        }

        $before = $occurrence->acknowledged_value;

        $occurrence->update([
            'acknowledged_at' => null,
            'acknowledged_by' => null,
            'acknowledged_value' => null,
        ]);

        $this->record($occurrence, 'alert_acknowledgement_revoked', $before, null);
        $this->engine->forget();

        return ['errors' => []];
    }

    private function open(int $occurrenceId): ?AlertOccurrence
    {
        /** @var AlertOccurrence|null */
        return AlertOccurrence::query()
            ->with('rule')
            ->whereNull('resolved_at')
            ->find($occurrenceId);
    }

    /**
     * Bieżąca wartość wystąpienia — z przebiegu, nie z bazy.
     *
     * W bazie leży wartość z chwili otwarcia i celowo nie jest
     * odświeżana. Do porównania „gorzej" potrzebna jest ta dzisiejsza.
     */
    private function current(AlertOccurrence $occurrence, ?Carbon $day = null): ?string
    {
        foreach ($this->engine->run($day) as $row) {
            if ((int) ($row['occurrence_id'] ?? 0) === (int) $occurrence->getKey()) {
                /** @var string|null $value */
                $value = $row['value'];

                return $value;
            }
        }

        // Wystapienie otwarte, ale nieobecne w dzisiejszym przebiegu —
        // zdarza sie miedzy przebiegiem a klikiem. Bierzemy wartosc
        // z otwarcia zamiast zgadywac.
        return $occurrence->value;
    }

    private function record(
        AlertOccurrence $occurrence,
        string $event,
        ?string $before,
        ?string $after,
    ): void {
        // Slad przy zleceniu, nie przy wystapieniu: dziennik czyta sie
        // przy sprawie, a nie przy wierszu tabeli alertow.
        $this->audit->write(
            (string) $occurrence->alertable_type,
            (int) $occurrence->alertable_id,
            [[
                // Bez domyslki: `alert_occurrences.alert_rule_id` jest
                // NOT NULL z kaskada, wiec wystapienie bez reguly nie
                // istnieje.
                'field' => 'alert: ' . $occurrence->rule->label,
                'before' => $before,
                'after' => $after,
            ]],
            $event,
        );
    }
}
