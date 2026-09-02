import type { ReactNode } from 'react';

type Props = {
  label: string;
  value?: ReactNode;
  note?: ReactNode;
  /** Ostrzeżenie w nocie — przekroczony limit, opóźnienie. */
  noteWarn?: boolean;
  text?: ReactNode;
  variant?: 'plain' | 'module' | 'money' | 'prod' | 'alert';
  wide?: boolean;
};

/**
 * Pasek decyzyjny w nagłówku ekranu.
 *
 * Wariant niesie znaczenie przez odcień: pieniądze są ochrą, produkcja
 * morska, zaległości terakotą. Dzięki temu ta sama liczba w innym
 * kontekście nie wygląda tak samo.
 */
export default function Strip({
  label,
  value,
  note,
  noteWarn,
  text,
  variant = 'plain',
  wide,
}: Props) {
  const className = [
    'ge-strip',
    variant === 'module' ? 'ge-strip--mod' : '',
    variant === 'money' ? 'ge-strip--money' : '',
    variant === 'prod' ? 'ge-strip--prod' : '',
    variant === 'alert' ? 'ge-strip--alert' : '',
    wide ? 'ge-strip--wide' : '',
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <div className={className}>
      <div className="ge-strip__label">{label}</div>
      {value !== undefined && <div className="ge-strip__value">{value}</div>}
      {text !== undefined && <div className="ge-strip__text">{text}</div>}
      {note !== undefined && (
        <div
          className={
            noteWarn ? 'ge-strip__note ge-strip__note--warn' : 'ge-strip__note'
          }
        >
          {note}
        </div>
      )}
    </div>
  );
}

export function Strips({ children }: { children: ReactNode }) {
  return <div className="ge-strips">{children}</div>;
}
