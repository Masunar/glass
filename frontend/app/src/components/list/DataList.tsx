import { type CSSProperties, type ReactNode, useEffect, useRef, useState } from 'react';

/**
 * Trzyma sygnał zapalony przez chwilę, nawet gdy odpowiedź wróciła od razu.
 *
 * Bez tego szybka odpowiedź daje mignięcie krótsze niż ruch oka —
 * a wtedy pasek jest gorszy niż jego brak, bo miga i nic nie mówi.
 * Trzysta milisekund to próg, poniżej którego człowiek nie zdąży
 * zauważyć, że coś się pojawiło.
 */
function useHeld(on: boolean, ms: number = 320): boolean {
  const [shown, setShown] = useState(on);
  const since = useRef(0);

  useEffect(() => {
    if (on) {
      since.current = Date.now();
      setShown(true);

      return;
    }

    const left = ms - (Date.now() - since.current);

    if (left <= 0) {
      setShown(false);

      return;
    }

    const timer = setTimeout(() => setShown(false), left);

    return () => clearTimeout(timer);
  }, [on, ms]);

  return shown;
}

export type Column = {
  labelKey?: string;
  label?: string;
  /** Szerokość kolumny w składni grid-template-columns, np. "96px", "1fr". */
  width: string;
  align?: 'left' | 'right';
};

type Props = {
  columns: Column[];
  children: ReactNode;
  /** Hala pracuje na większych celach dotykowych i większym numerze. */
  shop?: boolean;
  /** Trwa pobieranie — lista przygasa, a nad nią idzie pasek. */
  loading?: boolean;
  /** Co pokazać, gdy nic nie przyszło. Bez tego pusto znaczy dwie rzeczy. */
  empty?: ReactNode;
  style?: CSSProperties;
};

/**
 * Lista z siatką kolumn deklarowaną przez ekran.
 *
 * Siatka idzie do CSS jako zmienna `--cols`, więc każdy ekran deklaruje
 * własne szerokości, a całe zachowanie wiersza — gęstość, hover, pasma,
 * lewa krawędź stanu — jest wspólne. Komponenty tabelaryczne MUI nie
 * dają tej gęstości: wiersz ma tu 9 px pionu.
 */
export default function DataList({
  columns,
  children,
  shop,
  loading = false,
  empty,
  style,
}: Props) {
  const template = columns.map((column) => column.width).join(' ');
  const busy = useHeld(loading);

  return (
    <>
      <div className={busy ? 'ge-wait is-on' : 'ge-wait'} aria-hidden="true" />
      <div
        className={[
          'ge-list',
          shop ? 'ge-shop' : '',
          // Przygaszenie i zablokowanie klikniec: wiersz, ktory za
          // chwile zniknie, nie powinien dac sie kliknac.
          busy ? 'is-loading' : '',
        ]
          .filter(Boolean)
          .join(' ')}
        style={{ ['--cols' as string]: template, ...style }}
        aria-busy={busy}
      >
        {children}
        {!busy && empty !== undefined && (
          <div className="ge-list__empty">{empty}</div>
        )}
      </div>
    </>
  );
}

/**
 * Pasek „trwa pobieranie" — dwa piksele nad listą.
 *
 * Powstał z tego, że przełączanie zakładek wyglądało jak brak reakcji:
 * stare wiersze stały na ekranie, dopóki nie przyszły nowe, więc przy
 * szybkiej odpowiedzi nie było widać, że cokolwiek się stało, a przy
 * wolnej — że aplikacja żyje. **To ta sama rodzina co cicha awaria:**
 * stan bez własnego sygnału jest nie do odróżnienia od bezruchu.
 *
 * Wysokość jest stała, kolor znika — inaczej pasek przesuwałby listę
 * w dół przy każdym kliknięciu.
 */
export function ListWait({ on }: { on: boolean }) {
  const shown = useHeld(on);

  return (
    <div className={shown ? 'ge-wait is-on' : 'ge-wait'} aria-hidden="true" />
  );
}

export function ListHead({
  columns,
  translate,
}: {
  columns: Column[];
  translate: (key: string) => string;
}) {
  return (
    <div className="ge-list__head">
      {columns.map((column, index) => (
        <span
          key={column.labelKey ?? column.label ?? index}
          className={column.align === 'right' ? 'r' : undefined}
        >
          {column.labelKey ? translate(column.labelKey) : (column.label ?? '')}
        </span>
      ))}
    </div>
  );
}
