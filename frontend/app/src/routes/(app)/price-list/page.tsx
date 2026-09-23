import GroupDrawer from './_components/GroupDrawer';
import ProductDrawer from './_components/ProductDrawer';
import {
  computePrice,
  marginFromCoefficient,
  priceListSections,
} from './_components/sections';
import { useEffect, useMemo, useState } from 'react';
import { PiPencilSimple, PiPlus, PiWarningCircle } from 'react-icons/pi';

import { Button } from '@salvon/components/button';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifySuccess } from '@salvon/utils/notify';

import {
  type PriceCellInput,
  type PriceGroup,
  PriceListApi,
  type PriceMatrix,
  type PriceRow,
} from '@app/api/PriceListApi';
import HasPermission from '@app/components/HasPermission';
import { type Column, DataList, ListHead, Row } from '@app/components/list';
import { Permission, SubPermission } from '@app/config/permission';

const cellKey = (productId: number, sectionId: number) =>
  `${productId}_${sectionId}`;

/**
 * Cennik — macierz produkt × sekcja cenowa.
 *
 * W komórce siedzi **współczynnik**, nie cena. Cena wychodzi z ceny
 * zakupu przemnożonej przez współczynnik i jest liczona na żywo obok
 * pola, bo inaczej trzeba by zapisać, żeby zobaczyć, co się wpisało.
 * Obok stoi marża — ta sama informacja od drugiej strony, przydatna
 * przy przeglądaniu kolumny z góry na dół.
 *
 * **Ostrzeżenie o rozjeździe pokazuje się tylko wtedy, gdy jest**:
 * cena zakupu zmieniła się po ostatnim zapisie cennika, więc zapisana
 * cena sprzedaży przestała wynikać ze współczynnika. To nie jest błąd
 * do automatycznego naprawienia — przeliczenie jest decyzją człowieka.
 */
export default function Page() {
  const t = useTranslation();
  const form = useForm();
  const [matrix, setMatrix] = useState<PriceMatrix | null>(null);
  const [section, setSection] = useState<string>('glass');
  const [groupId, setGroupId] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(true);
  const [showInactive, setShowInactive] = useState(false);
  const [productDrawer, setProductDrawer] = useState<{
    open: boolean;
    row: PriceRow | null;
  }>({ open: false, row: null });
  const [groupDrawer, setGroupDrawer] = useState<{
    open: boolean;
    group: PriceGroup | null;
  }>({ open: false, group: null });

  const load = async (
    nextSection: string,
    nextGroupId: number | null,
    inactive: boolean = showInactive,
  ) => {
    setLoading(true);

    const { content } = await PriceListApi.matrix(
      nextSection,
      nextGroupId,
      inactive,
    );
    const data: PriceMatrix | undefined = content?.data;

    setLoading(false);

    if (!data) {
      return;
    }

    setMatrix(data);
    setGroupId(data.group_id);
    form.reset(
      Object.fromEntries(
        data.rows.flatMap((row) =>
          data.columns.map((column) => [
            cellKey(row.product_id, column.id),
            row.cells[String(column.id)]?.coefficient ?? '',
          ]),
        ),
      ),
    );
  };

  useEffect(() => {
    void load(section, null);
  }, [section]);

  const values = form.watch();
  const dirtyKeys = Object.keys(form.formState.dirtyFields);

  /** Produkt, cena zakupu, potem po jednej kolumnie na sekcję cenową. */
  const columns: Column[] = useMemo(
    () => [
      {
        labelKey: 'page.price_list.column.product',
        width: 'minmax(220px, 1fr)',
      },
      {
        labelKey: 'page.price_list.column.purchase',
        width: '120px',
        align: 'right',
      },
      ...(matrix?.columns ?? []).map((column) => ({
        // Domyslna sekcja oznaczona kropka, nie pigulka: w naglowku
        // tabeli pigulka wazy tyle co nazwa, ktora oznacza.
        label: column.is_default ? `${column.name} ·` : column.name,
        width: '210px',
      })),
    ],
    [matrix],
  );

  const save = async () => {
    if (dirtyKeys.length === 0) {
      return;
    }

    setSaving(true);

    // Wysylamy wylacznie zmienione komorki. Zapis jest niepodzielny —
    // odrzucenie jednej wartosci wstrzymuje pozostale.
    const data = form.getValues();
    const cells: PriceCellInput[] = dirtyKeys.map((key) => {
      const [productId, sectionId] = key.split('_').map(Number);
      const raw = String(data[key] ?? '').replace(',', '.');

      return {
        product_id: productId,
        price_section_id: sectionId,
        coefficient: raw === '' ? null : raw,
        manual_net_price: null,
      };
    });

    const { content } = await PriceListApi.save(cells);

    setSaving(false);

    if (!validationCompleted(content, form.setError, t)) {
      return;
    }

    notifySuccess(t('page.price_list.saved'));
    await load(section, groupId);
  };

  const groups = matrix?.groups ?? [];
  const rows = matrix?.rows ?? [];

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">{t('page.module.zlec')}</div>
          <h1 className="ge-head__title">{t('page.price_list.title')}</h1>
          <div className="ge-quiet">{t('page.price_list.lead')}</div>
        </div>

        <div className="ge-head__actions">
          <HasPermission
            permission={Permission.PRICE_LIST}
            sub={SubPermission.UPDATE}
          >
            <Button
              variant="contained"
              loading={saving}
              disabled={dirtyKeys.length === 0}
              onClick={() => void save()}
            >
              {dirtyKeys.length > 0
                ? t('page.price_list.save_count', { count: dirtyKeys.length })
                : t('save')}
            </Button>
          </HasPermission>
        </div>
      </header>

      <div className="ge-segbar">
        <nav
          className="ge-seg ge-seg--nav"
          aria-label={t('page.price_list.title')}
        >
          {priceListSections.map((item) => (
            <button
              key={item.value}
              type="button"
              className={
                item.value === section
                  ? 'ge-seg__item is-active'
                  : 'ge-seg__item'
              }
              aria-pressed={item.value === section}
              onClick={() => setSection(item.value)}
            >
              <span>{t(item.labelKey)}</span>
            </button>
          ))}
        </nav>

        <span className="ge-segbar__end ge-pl__end">
          {/* Legenda przy kropce: nieopisany znak to sygnal, ktorego
              nikt nie odczyta — a wlasnie takie zbieramy tu od
              poczatku jako ciche awarie. */}
          <span className="ge-quiet">{t('page.price_list.default_hint')}</span>
          <label className="ge-toggle">
            <input
              type="checkbox"
              checked={showInactive}
              onChange={(event) => {
                setShowInactive(event.target.checked);
                void load(section, groupId, event.target.checked);
              }}
            />
            <span className="ge-toggle__track" />
            {t('page.price_list.show_inactive')}
          </label>
        </span>
      </div>

      <div className="ge-card">
        <aside className="ge-card__side">
          <section className="ge-section">
            <div className="ge-section__head">
              {t('page.price_list.group.section')}
            </div>

            {groups.length === 0 && (
              <div className="ge-quiet">{t('page.price_list.no_groups')}</div>
            )}

            {groups.map((group) => (
              <div
                key={group.id}
                className={
                  group.id === groupId
                    ? 'ge-pl__group is-active'
                    : 'ge-pl__group'
                }
              >
                <button
                  type="button"
                  className={
                    group.is_active
                      ? 'ge-pl__group-name'
                      : 'ge-pl__group-name is-off'
                  }
                  onClick={() => {
                    setGroupId(group.id);
                    void load(section, group.id);
                  }}
                >
                  {group.name}
                </button>

                <HasPermission
                  permission={Permission.PRODUCTS}
                  sub={SubPermission.UPDATE}
                >
                  <button
                    type="button"
                    className="ge-pl__group-edit"
                    aria-label={t('page.price_list.group.edit')}
                    onClick={() => setGroupDrawer({ open: true, group })}
                  >
                    <PiPencilSimple />
                  </button>
                </HasPermission>
              </div>
            ))}

            <HasPermission
              permission={Permission.PRODUCTS}
              sub={SubPermission.CREATE}
            >
              <Button
                variant="text"
                size="small"
                icon={<PiPlus />}
                onClick={() => setGroupDrawer({ open: true, group: null })}
              >
                {t('page.price_list.group.add')}
              </Button>
            </HasPermission>
          </section>
        </aside>

        <div className="ge-card__main">
          <section className="ge-section">
            <DataList
              columns={columns}
              loading={loading}
              empty={
                rows.length === 0 ? t('page.price_list.no_rows') : undefined
              }
              style={{ overflowX: 'auto' }}
            >
              <ListHead columns={columns} translate={t} />

              {rows.map((row) => (
                <Row key={row.product_id}>
                  <div className="ge-pl__product">
                    <span
                      className={row.is_active ? 'ge-name' : 'ge-name ge-muted'}
                    >
                      {row.name}
                    </span>
                    <HasPermission
                      permission={Permission.PRODUCTS}
                      sub={SubPermission.UPDATE}
                    >
                      <button
                        type="button"
                        className="ge-pl__group-edit"
                        aria-label={t('page.price_list.product.edit')}
                        onClick={() => setProductDrawer({ open: true, row })}
                      >
                        <PiPencilSimple />
                      </button>
                    </HasPermission>
                  </div>

                  <div className="r">
                    {row.purchase_net_price ?? (
                      /* Brak ceny zakupu nie jest zerem: bez niej
                       wspolczynnik nie ma czego pomnozyc. */
                      <span className="ge-note ge-note--warn">
                        <PiWarningCircle />{' '}
                        {t('page.price_list.no_purchase_price')}
                      </span>
                    )}
                  </div>

                  {(matrix?.columns ?? []).map((column) => {
                    const key = cellKey(row.product_id, column.id);
                    const cell = row.cells[String(column.id)];
                    const typed = String(values[key] ?? '');
                    const preview = computePrice(row.purchase_net_price, typed);
                    const margin = marginFromCoefficient(typed);
                    const isDirty = dirtyKeys.includes(key);

                    return (
                      <div className="ge-pl__cell" key={column.id}>
                        <input
                          {...form.register(key)}
                          className="ge-pl__input"
                          inputMode="decimal"
                          placeholder="—"
                          aria-label={`${row.name} — ${column.name}`}
                        />
                        <span
                          className={
                            isDirty ? 'ge-pl__price is-dirty' : 'ge-pl__price'
                          }
                        >
                          {preview ?? '—'}
                        </span>
                        {margin !== null && (
                          <span className="ge-pl__margin">
                            {t('page.price_list.margin', { value: margin })}
                          </span>
                        )}
                        {cell?.is_stale && !isDirty && (
                          <span className="ge-pl__stale">
                            {t('page.price_list.stale', {
                              value: cell.recomputed_net_price ?? '',
                            })}
                          </span>
                        )}
                      </div>
                    );
                  })}
                </Row>
              ))}
            </DataList>

            <HasPermission
              permission={Permission.PRODUCTS}
              sub={SubPermission.CREATE}
            >
              <div className="ge-pl__foot">
                <Button
                  variant="text"
                  size="small"
                  icon={<PiPlus />}
                  disabled={groupId === null}
                  onClick={() => setProductDrawer({ open: true, row: null })}
                >
                  {t('page.price_list.product.add')}
                </Button>
              </div>
            </HasPermission>
          </section>
        </div>
      </div>

      <ProductDrawer
        section={section}
        groupId={groupId}
        row={productDrawer.row}
        open={productDrawer.open}
        onClose={() => setProductDrawer({ open: false, row: null })}
        onSaved={() => {
          setProductDrawer({ open: false, row: null });
          void load(section, groupId);
        }}
      />

      <GroupDrawer
        section={section}
        group={groupDrawer.group}
        open={groupDrawer.open}
        onClose={() => setGroupDrawer({ open: false, group: null })}
        onSaved={(savedId) => {
          setGroupDrawer({ open: false, group: null });
          setGroupId(savedId);
          void load(section, savedId);
        }}
      />
    </>
  );
}
