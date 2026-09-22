<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use App\Models\GlobalParameter;

/**
 * Podstawianie wartości w tekstach ofertowych.
 *
 * Teksty drukowane klientowi odwołują się do parametrów przez
 * `{{klucz}}` — po to, żeby liczba i jej opis nie mogły się rozjechać.
 * W starym systemie ważność oferty wynosiła 10 dni w polu i 7 dni
 * w tekście; klient dostawał jedną informację, system pilnował innej.
 * `GlobalParameterService` pilnuje, żeby szablon odwoływał się tylko
 * do istniejących parametrów, ale **nic ich dotąd nie podstawiało**.
 *
 * **Tekst z pustym odwołaniem nie powstaje w ogóle.** „Warunki
 * płatności: przedpłata na rachunek ” bez numeru to zdanie urwane
 * w pół — gorsze niż brak tego wiersza, bo wygląda na kompletne.
 * Dosłowne `{{bank_account_iban}}` na wydruku byłoby jeszcze gorsze.
 */
final readonly class ParameterTemplate
{
    private const PLACEHOLDER = '/\{\{\s*([a-z0-9_]+)\s*\}\}/i';

    /**
     * Tekst z podstawionymi wartościami albo `null`, gdy któregokolwiek
     * z odwołań nie da się wypełnić.
     */
    public static function render(?string $template, ?Carbon $on = null): ?string
    {
        if ($template === null || trim($template) === '') {
            return null;
        }

        $on ??= Carbon::today();
        $missing = false;

        $rendered = preg_replace_callback(
            self::PLACEHOLDER,
            static function (array $match) use ($on, &$missing): string {
                $value = GlobalParameter::value((string) $match[1], $on);

                if ($value === null || trim($value) === '') {
                    $missing = true;

                    return '';
                }

                return $value;
            },
            $template,
        );

        if ($missing || $rendered === null) {
            return null;
        }

        return $rendered;
    }
}
