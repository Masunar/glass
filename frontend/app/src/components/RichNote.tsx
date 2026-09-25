import type { ReactNode } from 'react';

/**
 * Pogrubienie w komentarzach zlecenia: fragment otoczony dwiema
 * gwiazdkami (`**pilne**`). W bazie zostaje zwykly tekst, nie HTML.
 *
 * Ten sam wzorzec czyta backend (`App\Support\RichNote`) dla wydruku
 * oferty — zmiana tutaj bez zmiany tam rozjedzie karte z PDF-em.
 */
const BOLD = /\*\*([^\n]+?)\*\*/g;

export type NotePart = { text: string; bold: boolean };

/** Tekst pociety na zwykle i pogrubione kawalki, w kolejnosci wpisania. */
export function noteParts(text: string): NotePart[] {
  const parts: NotePart[] = [];
  let last = 0;

  for (const match of text.matchAll(BOLD)) {
    const at = match.index ?? 0;

    if (at > last) {
      parts.push({ text: text.slice(last, at), bold: false });
    }

    parts.push({ text: match[1], bold: true });
    last = at + match[0].length;
  }

  if (last < text.length) {
    parts.push({ text: text.slice(last), bold: false });
  }

  return parts;
}

/** Sam tekst, bez gwiazdek — do podpowiedzi i atrybutu `title`. */
export function plainNote(text: string): string {
  return text.replace(BOLD, '$1');
}

function render(parts: NotePart[]): ReactNode[] {
  return parts.map((part, index) =>
    part.bold ? <strong key={index}>{part.text}</strong> : part.text,
  );
}

/** Komentarz z pogrubieniami, tak jak go wpisano. */
export default function RichNote({ text }: { text: string }) {
  return <>{render(noteParts(text))}</>;
}

/**
 * Uwaga w wierszu listy: dwie linie, pogrubione fragmenty na poczatku.
 *
 * Pogrubia sie to, czego nie wolno przeoczyc — w wierszu, ktory
 * i tak ucina tekst, ma to stac przed reszta, a nie w ucietym ogonie.
 * Pelna tresc, w kolejnosci wpisania, jest w podpowiedzi.
 */
export function NoteLine({ text }: { text: string }) {
  const parts = noteParts(text);
  const bold = parts.filter((part) => part.bold);
  const rest = parts
    .filter((part) => !part.bold)
    .map((part) => part.text)
    .join(' ')
    .replace(/\s+/g, ' ')
    // Po wyjeciu pogrubien zostaja przecinki i myslniki na poczatku.
    .replace(/^[\s,.;:–—-]+/, '')
    .trim();

  return (
    <div className="ge-note ge-note--rich" title={plainNote(text)}>
      {bold.length === 0 ? (
        text
      ) : (
        <>
          {bold.map((part, index) => (
            <span key={index}>
              {index > 0 && ' · '}
              <strong>{part.text}</strong>
            </span>
          ))}
          {rest !== '' && ` — ${rest}`}
        </>
      )}
    </div>
  );
}

/**
 * Otacza zaznaczenie gwiazdkami albo je z niego zdejmuje.
 *
 * Spacje na brzegach zaznaczenia zostaja poza gwiazdkami — `** tekst**`
 * nie jest juz pogrubieniem. Bez zaznaczenia wstawia pare gwiazdek
 * i stawia kursor miedzy nimi.
 */
export function toggleBold(
  value: string,
  start: number,
  end: number,
): { value: string; start: number; end: number } {
  let from = start;
  let to = end;

  while (from < to && /\s/.test(value[from])) {
    from++;
  }

  while (to > from && /\s/.test(value[to - 1])) {
    to--;
  }

  const before = value.slice(0, from);
  const selected = value.slice(from, to);
  const after = value.slice(to);

  if (before.endsWith('**') && after.startsWith('**')) {
    return {
      value: before.slice(0, -2) + selected + after.slice(2),
      start: from - 2,
      end: to - 2,
    };
  }

  if (
    selected.startsWith('**') &&
    selected.endsWith('**') &&
    selected.length >= 4
  ) {
    const inner = selected.slice(2, -2);

    return {
      value: before + inner + after,
      start: from,
      end: from + inner.length,
    };
  }

  return {
    value: `${before}**${selected}**${after}`,
    start: from + 2,
    end: to + 2,
  };
}
