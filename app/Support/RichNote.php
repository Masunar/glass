<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pogrubienie w komentarzach zlecenia.
 *
 * Komentarz zostaje zwykłym tekstem — pogrubiony fragment otaczają
 * dwie gwiazdki, jak w wiadomościach (`**pilne**`). Żadnego HTML-a
 * w bazie: tekst z telefonu nie może wstrzyknąć znacznika do PDF-u,
 * a wyszukiwanie i dziennik zmian widzą to, co wpisano.
 *
 * Front ma własne odczytanie tych samych znaczników
 * (`components/RichNote.tsx`); wzorzec musi zostać ten sam, inaczej
 * karta i oferta pokażą co innego.
 */
final readonly class RichNote
{
    /** Najkrótszy fragment między parą gwiazdek, bez przejścia do nowej linii. */
    private const BOLD = '/\*\*([^\n]+?)\*\*/u';

    /**
     * HTML do wydruku: tekst zabezpieczony, pogrubienia jako `<strong>`,
     * nowe linie jako `<br>`.
     */
    public static function html(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $bold = preg_replace(self::BOLD, '<strong>$1</strong>', $escaped) ?? $escaped;

        return nl2br($bold, false);
    }

    /** Sam tekst, bez znaczników — do wycinków i podpowiedzi. */
    public static function plain(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        return preg_replace(self::BOLD, '$1', $text) ?? $text;
    }
}
