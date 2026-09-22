import type { DriftBoard } from '@app/api/WarehouseApi';
import HasPermission from '@app/components/HasPermission';
import { Permission, SubPermission } from '@app/config/permission';

type Translate = (key: string, options?: Record<string, unknown>) => string;

/**
 * Produkty, których cena zakupu wyprzedziła cennik sprzedaży.
 *
 * Przyjęcie towaru aktualizuje cenę zakupu, ale cen sprzedaży nie rusza
 * — inaczej jedna dostawa przestawiłaby oferty już wysłane do klienta.
 * Ten ekran jest jedynym, co o rozjeździe mówi, więc pokazuje **starą
 * i nową cenę zakupu obok siebie**: skoro cena bierze się z ostatniej
 * dostawy, uzupełnienie stanu drożej zawyża koszt tego, co już leżało,
 * i człowiek musi to zobaczyć, zanim naciśnie „przelicz".
 */
export function PriceDrift({
  board,
  t,
  selected,
  onToggle,
  onClear,
  onRecalculate,
}: {
  board: DriftBoard | null;
  t: Translate;
  selected: number[];
  onToggle: (productId: number) => void;
  onClear: () => void;
  onRecalculate: () => void;
}) {
  const rows = board?.rows ?? [];

  return (
    <>
      {/* Przycisk widzi tylko ten, kto moze zmieniac ceny sprzedazy.
          Zaopatrzeniowiec ma widziec rozjazd i nie miec czym go
          przeliczyc — ta sama bramka stoi po stronie serwera. */}
      <HasPermission permission={Permission.PRICE_LIST} sub={SubPermission.UPDATE}>
        <div className="ge-segbar">
          <span className="ge-quiet">{t('page.warehouse.drift_lead')}</span>
          {selected.length > 0 && (
            <span className="ge-segbar__end ge-stock__bulk">
              <span className="ge-quiet">
                {t('page.warehouse.selected', { count: selected.length })}
              </span>
              <button type="button" onClick={onClear}>
                {t('page.warehouse.clear_selection')}
              </button>
              <button type="button" className="ge-act--go" onClick={onRecalculate}>
                {t('page.warehouse.recalculate')}
              </button>
            </span>
          )}
        </div>
      </HasPermission>

    <div className="ge-stock ge-stock--drift">
      <div className="ge-stock__head">
        <span />
        <span>{t('page.warehouse.column.code')}</span>
        <span>{t('page.warehouse.column.name')}</span>
        <span className="r">{t('page.warehouse.column.purchase_was')}</span>
        <span className="r">{t('page.warehouse.column.purchase_now')}</span>
        <span className="r">{t('page.warehouse.column.changed_at')}</span>
        <span className="r">{t('page.warehouse.column.coefficient')}</span>
        <span className="r">{t('page.warehouse.column.list_price')}</span>
        <span className="r">{t('page.warehouse.column.new_list_price')}</span>
        <span className="r">{t('page.warehouse.column.priced_at')}</span>
      </div>

      {rows.map((row) => (
        <div className="ge-stock__row ge-stock__row--short" key={row.product_id}>
          <span>
            {/* Pozycja bez wspolczynnika nie ma z czego sie przeliczyc. */}
            <input
              type="checkbox"
              checked={selected.includes(row.product_id)}
              disabled={row.new_list_price === null}
              onChange={() => onToggle(row.product_id)}
              aria-label={row.name}
            />
          </span>
          <span className="ge-quiet">{row.code ?? '—'}</span>
          <span className="ge-cell--wrap">{row.name}</span>
          <span className="r ge-quiet">{row.previous_purchase_price ?? '—'}</span>
          <span className="r ge-note--warn">{row.purchase_price}</span>
          <span className="r ge-quiet">{row.changed_at}</span>
          <span className="r ge-quiet">{row.coefficient}</span>
          <span className="r">{row.list_net_price ?? '—'}</span>
          {/* Cena reczna nie zmieni sie po przeliczeniu — mowimy to
              wprost, zamiast pokazywac liczbe, ktora nie wejdzie. */}
          <span className={row.is_manual ? 'r ge-quiet' : 'r ge-dim'}>
            {row.is_manual
              ? t('page.warehouse.manual_stays')
              : (row.new_list_price ?? '—')}
          </span>
          <span className="r ge-quiet">{row.priced_at}</span>
        </div>
      ))}

      {rows.length === 0 && (
        <div className="ge-empty">{t('page.warehouse.drift_empty')}</div>
      )}
    </div>
    </>
  );
}
