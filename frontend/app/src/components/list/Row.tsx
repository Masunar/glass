import type { KeyboardEvent, ReactNode } from 'react';
import { useNavigate } from 'react-router';

type Props = {
  children: ReactNode;
  /** Dokąd prowadzi wiersz. Bez tego wiersz nie jest klikalny. */
  to?: string;
  selected?: boolean;
  /** Wiersz wymagający decyzji — lewa krawędź w terakocie. */
  alert?: boolean;
};

/**
 * Wiersz listy.
 *
 * Klikalny i fokusowalny, ale nie jest linkiem `<a>`: w środku siedzą
 * przyciski „co dalej", a przycisk wewnątrz linku to nieprawidłowy HTML
 * i pułapka dla czytników ekranu. Stąd rola i obsługa klawiatury wprost.
 */
export default function Row({ children, to, selected, alert }: Props) {
  const navigate = useNavigate();

  const className = [
    'ge-row',
    selected ? 'is-selected' : '',
    alert ? 'is-alert' : '',
    to ? '' : 'is-static',
  ]
    .filter(Boolean)
    .join(' ');

  const open = () => {
    if (to) {
      void navigate(to);
    }
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    // Klawisz na przycisku wewnatrz wiersza nalezy do przycisku.
    if (event.target !== event.currentTarget) {
      return;
    }

    event.preventDefault();
    open();
  };

  return (
    <div
      className={className}
      role={to ? 'link' : undefined}
      tabIndex={to ? 0 : undefined}
      onClick={to ? open : undefined}
      onKeyDown={to ? handleKeyDown : undefined}
    >
      {children}
    </div>
  );
}

/** Etap: kropka i słowo. Bez pigułek — kolor niesie jedną informację. */
export function Stage({
  label,
  tone = 'new',
}: {
  label: string;
  tone?: 'new' | 'prod' | 'done' | 'claim' | 'idle';
}) {
  return (
    <span className="ge-stage">
      <span className={tone === 'idle' ? 'ge-dot' : `ge-dot ge-dot--${tone}`} />
      {label}
    </span>
  );
}

/** Termin słowem: „dziś", „2 dni po terminie" — nie surowa data. */
export function Due({
  label,
  tone = 'plain',
}: {
  label: string;
  tone?: 'plain' | 'today' | 'late';
}) {
  return (
    <span className={tone === 'plain' ? 'ge-due' : `ge-due ge-due--${tone}`}>
      {label}
    </span>
  );
}

/**
 * Kwota z paskiem zapłaty.
 *
 * Przy zerze pasek zostaje widoczny jako tło — inaczej wiersz bez wpłaty
 * wyglądałby jak wiersz bez informacji.
 */
export function Money({
  value,
  paidPercent,
  tone = 'module',
}: {
  value: string;
  paidPercent: number;
  tone?: 'module' | 'prod' | 'done' | 'alert';
}) {
  const clamped = Math.max(0, Math.min(100, paidPercent));

  return (
    <div className="ge-money">
      <div className="ge-money__value">{value}</div>
      <div className="ge-bar">
        <div
          className={
            tone === 'module'
              ? 'ge-bar__fill'
              : `ge-bar__fill ge-bar__fill--${tone}`
          }
          style={{ width: `${clamped}%` }}
        />
      </div>
      <div className="ge-money__note">{clamped} % zapłacone</div>
    </div>
  );
}
