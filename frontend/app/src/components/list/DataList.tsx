import type { CSSProperties, ReactNode } from 'react';

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
export default function DataList({ columns, children, shop, style }: Props) {
  const template = columns.map((column) => column.width).join(' ');

  return (
    <div
      className={shop ? 'ge-list ge-shop' : 'ge-list'}
      style={{ ['--cols' as string]: template, ...style }}
    >
      {children}
    </div>
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
