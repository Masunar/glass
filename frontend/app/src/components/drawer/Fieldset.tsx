import type { ReactNode } from 'react';

type Tone = 'ident' | 'addr' | 'contact' | 'terms';

/**
 * Sekcja formularza na pasku koloru roli.
 *
 * Kolor niesie znaczenie, tak jak na listwie modułów: adres to ta sama
 * barwa co produkcja, warunki handlowe co księgowość. Dzięki temu oko
 * wie, do czyjej sprawy należy pole, zanim przeczyta etykietę.
 */
export default function Fieldset({
  tone,
  label,
  children,
}: {
  tone: Tone;
  label: string;
  children: ReactNode;
}) {
  return (
    <div className={`ge-fs ge-fs--${tone}`}>
      <div className="ge-fs__label">{label}</div>
      {children}
    </div>
  );
}

export function FieldRow({
  columns,
  children,
  paddingTop,
}: {
  columns: string;
  children: ReactNode;
  paddingTop?: number;
}) {
  return (
    <div
      className="ge-fs__grid"
      style={{ gridTemplateColumns: columns, paddingTop }}
    >
      {children}
    </div>
  );
}

export function FieldNote({ children }: { children: ReactNode }) {
  return <div className="ge-fs__note">{children}</div>;
}
