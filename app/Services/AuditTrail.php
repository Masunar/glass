<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditEntry;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

/**
 * Dziennik zmian — jedno miejsce zapisu dla całej aplikacji.
 *
 * Wcześniej ta sama logika stała w trzech serwisach w trzech kopiach.
 * Kopie nie rozjechały się jeszcze tylko dlatego, że nikt ich nie
 * ruszał: pierwsza poprawka w jednej z nich dałaby trzy różne dzienniki
 * dla tego samego zdarzenia.
 *
 * Rejestr jest wyłącznie do dopisywania. Zapisuje wartość przed i po
 * dla każdego zmienionego pola — stary log rejestrował sam fakt zmiany
 * („Zmienił rabaty"), przez co nie dawał odpowiedzi na jedyne pytanie,
 * jakie się do niego kieruje: skąd wzięła się ta cena.
 */
final readonly class AuditTrail
{
    /**
     * Zapisuje różnicę między stanem przed i po.
     *
     * `$before` równe null oznacza nowy rekord — wtedy wszystkie pola
     * są zmianą i zdarzenie to „created".
     *
     * @param array<string, mixed>|null $before
     * @param array<string, mixed> $after
     */
    public function record(string $type, int $id, ?array $before, array $after): void
    {
        $changes = [];

        foreach ($after as $field => $value) {
            $previous = $before[$field] ?? null;

            // Pole niezmienione nie trafia do dziennika — inaczej każdy
            // zapis formularza produkowałby ścianę wierszy bez treści.
            if ($before !== null && $previous === $value) {
                continue;
            }

            $changes[] = ['field' => $field, 'before' => $previous, 'after' => $value];
        }

        if ($changes === []) {
            return;
        }

        $this->write($type, $id, $changes, $before === null ? 'created' : 'updated');
    }

    /**
     * Zapisuje gotową listę zmian.
     *
     * Dla przypadków, w których różnicy nie da się wyliczyć z dwóch
     * tablic — jak parametry wyceny, gdzie każda zmiana zakłada nową
     * wersję wiersza, a stara wartość pochodzi z wersji zamykanej.
     *
     * @param list<array{field: string, before: mixed, after: mixed}> $changes
     */
    public function write(string $type, int $id, array $changes, string $event = 'updated'): void
    {
        if ($changes === []) {
            return;
        }

        AuditEntry::query()->create([
            // Jeden zapis to jedna sesja edycji: zmiany z jednego
            // kliknięcia mają być w dzienniku jednym wpisem.
            'edit_session_id' => (string) Str::uuid(),
            'auditable_type' => $type,
            'auditable_id' => $id,
            'user_id' => Auth::id(),
            'event' => $event,
            'changes' => $changes,
            'ip_address' => request()->ip(),
        ]);
    }
}
