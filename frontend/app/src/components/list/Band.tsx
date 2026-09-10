import type { ReactNode } from 'react';

type Props = {
  title: string;
  meta?: string;
  end?: ReactNode;
  /** Pasmo modułu (odcień bieżącego modułu) albo alarmowe (terakota). */
  variant?: 'plain' | 'module' | 'alert';
};

/**
 * Pasmo pilności — „Dziś", „Zaległe", „Kolejne dni".
 *
 * Zastępuje sortowanie jako główny sposób czytania listy: zamiast
 * układać wiersze po jednej kolumnie, dzieli je na to, co wymaga
 * decyzji dzisiaj, i całą resztę. Suma kwot w pasmie jest częścią
 * informacji, nie ozdobą — mówi, ile pieniędzy stoi za tą decyzją.
 */
export default function Band({ title, meta, end, variant = 'plain' }: Props) {
  const className = [
    'ge-band',
    variant === 'module' ? 'ge-band--mod' : '',
    variant === 'alert' ? 'ge-band--alert' : '',
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <div className={className}>
      <span className="ge-band__title">{title}</span>
      {meta && <span className="ge-band__meta">{meta}</span>}
      {end && <span className="ge-band__end">{end}</span>}
    </div>
  );
}
