import { decimal } from '../../magazyn/_components/decimal';
import { Link } from 'react-router';

import type { TemperingQueue } from '@app/api/TemperingApi';

type Translate = (key: string, options?: Record<string, unknown>) => string;

/**
 * Kolejka do hartowni: co czeka na wsad.
 *
 * Sumy liczą się z **zaznaczonych** wierszy, bo po to się tu wchodzi —
 * operator kompletuje partię do konkretnej wielkości. Obok kilogramów
 * stoi m², bo od tego zwykle rozlicza się podwykonawca, i liczba
 * różnych grubości: piec ustawia się pod jedną, więc mieszanka jest
 * sygnałem kosztu, a nie szczegółem.
 */
export function Queue({
  board,
  t,
  thickness,
  onThickness,
  selected,
  onToggle,
  onClear,
  onCreateBatch,
}: {
  board: TemperingQueue | null;
  t: Translate;
  thickness: string;
  onThickness: (value: string) => void;
  selected: number[];
  onToggle: (id: number) => void;
  onClear: () => void;
  onCreateBatch: () => void;
}) {
  const rows = board?.rows ?? [];
  const picked = rows.filter((row) => selected.includes(row.id));

  const kg = picked.reduce((total, row) => total + row.kg, 0);
  const m2 = picked.reduce((total, row) => total + row.m2, 0);
  const thicknesses = new Set(
    picked.map((row) => row.thickness_mm).filter((value) => value !== null),
  );

  return (
    <>
      <div className="ge-segbar">
        <nav
          className="ge-seg ge-seg--filter"
          aria-label={t('page.tempering.thickness')}
        >
          <button
            type="button"
            className={
              thickness === '' ? 'ge-seg__item is-active' : 'ge-seg__item'
            }
            aria-pressed={thickness === ''}
            onClick={() => onThickness('')}
          >
            <span>{t('page.tempering.all_thicknesses')}</span>
          </button>
          {(board?.thicknesses ?? []).map((value) => (
            <button
              key={value}
              type="button"
              className={
                thickness === String(value)
                  ? 'ge-seg__item is-active'
                  : 'ge-seg__item'
              }
              aria-pressed={thickness === String(value)}
              onClick={() => onThickness(String(value))}
            >
              <span>{decimal(value)} mm</span>
            </button>
          ))}
        </nav>

        {selected.length > 0 && (
          <span className="ge-segbar__end ge-stock__bulk">
            <span className="ge-quiet">
              {t('page.tempering.picked', { count: picked.length })}
            </span>
            <span className="ge-temp__sum">
              <strong>{decimal(Math.round(kg * 100) / 100)}</strong> kg
            </span>
            <span className="ge-temp__sum">
              <strong>{decimal(Math.round(m2 * 1000) / 1000)}</strong> m²
            </span>
            {/* Mieszanka grubosci w jednym wsadzie podnosi koszt
                i ryzyko — hartownia ustawia piec pod jedna. */}
            <span
              className={
                thicknesses.size > 1
                  ? 'ge-temp__sum ge-note--warn'
                  : 'ge-temp__sum'
              }
            >
              {t('page.tempering.thickness_count', { count: thicknesses.size })}
            </span>
            <button type="button" onClick={onClear}>
              {t('page.warehouse.clear_selection')}
            </button>
            <button
              type="button"
              className="ge-act--go"
              onClick={onCreateBatch}
            >
              {t('page.tempering.create_batch')}
            </button>
          </span>
        )}
      </div>

      <div className="ge-stock ge-stock--queue">
        <div className="ge-stock__head">
          <span />
          <span className="r">{t('page.tempering.column.order')}</span>
          <span>{t('page.tempering.column.contractor')}</span>
          <span>{t('page.tempering.column.glass')}</span>
          <span className="r">{t('page.tempering.column.thickness')}</span>
          <span className="r">{t('page.tempering.column.size')}</span>
          <span className="r">{t('page.tempering.column.quantity')}</span>
          <span className="r">{t('page.tempering.column.kg')}</span>
          <span className="r">{t('page.tempering.column.m2')}</span>
          <span className="r">{t('page.tempering.column.deadline')}</span>
          <span>{t('page.tempering.column.flags')}</span>
        </div>

        {rows.map((row) => (
          <div
            className={
              selected.includes(row.id)
                ? 'ge-stock__row is-open'
                : 'ge-stock__row'
            }
            key={row.id}
          >
            <span>
              <input
                type="checkbox"
                checked={selected.includes(row.id)}
                onChange={() => onToggle(row.id)}
                aria-label={row.name}
              />
            </span>
            <span className="r">
              <Link to={`/orders/${row.order_id}`} className="ge-link">
                #{row.order_number}
              </Link>
            </span>
            <span className="ge-cell--wrap">{row.contractor ?? '—'}</span>
            <span className="ge-cell--wrap">{row.name}</span>
            <span className="r ge-quiet">
              {row.thickness_mm === null
                ? '—'
                : `${decimal(row.thickness_mm)} mm`}
            </span>
            <span className="r ge-quiet">
              {row.width_mm} × {row.height_mm}
            </span>
            <span className="r">{decimal(row.quantity)}</span>
            <span className="r ge-quiet">{decimal(row.kg)}</span>
            <span className="r ge-quiet">{decimal(row.m2)}</span>
            {/* Termin decyduje, co ma jechac tym kursem. Pilne stoi
                i tak na gorze, wiec tutaj wystarczy data. */}
            <span className="r ge-quiet">{row.deadline ?? '—'}</span>
            <span className="ge-temp__flags">
              {/* Pilne wygrywa z terminem — ta sama regula, co na hali. */}
              {row.is_urgent && (
                <span className="ge-tag ge-tag--urgent">
                  {t('page.tempering.urgent')}
                </span>
              )}
              {row.shape !== 'rectangle' && (
                <span className="ge-tag">
                  {t(`page.orders.shape.${row.shape}`)}
                </span>
              )}
              {/* Znak jest sygnalem dla operatora przy wysylce. */}
              {row.needs_mark && (
                <span className="ge-tag ge-tag--mark">
                  {t('page.tempering.mark')}
                </span>
              )}
              {/* Pozycja zastepcza po stluczce — ta sama formatka
                  jedzie do pieca drugi raz i widac to wprost. */}
              {row.replaces_id !== null && (
                <span className="ge-tag ge-tag--again">
                  {t('page.tempering.again')}
                </span>
              )}
            </span>
          </div>
        ))}

        {rows.length === 0 && (
          <div className="ge-empty">{t('page.tempering.queue_empty')}</div>
        )}
      </div>
    </>
  );
}
