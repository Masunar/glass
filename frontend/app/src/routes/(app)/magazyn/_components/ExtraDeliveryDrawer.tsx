import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type { ExtraDeliveryBoard, PickingRow } from '@app/api/WarehouseApi';
import { WarehouseApi } from '@app/api/WarehouseApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Fieldset, { FieldNote } from '@app/components/drawer/Fieldset';

type Translate = (key: string, options?: Record<string, unknown>) => string;

/**
 * Nowa dostawa dodatkowa dla zlecenia z listy kompletacji.
 *
 * Pozycje do wyboru to okucia tego zlecenia — reklamacja, błędne
 * okucie i domówienie dotyczą tego, co na zleceniu już jest. Ilość
 * startuje od ilości na zleceniu i da się ją zmniejszyć (jedna
 * uszkodzona klamka z czterech).
 */
export function ExtraDeliveryDrawer({
  order,
  t,
  onClose,
  onSaved,
}: {
  order: PickingRow | null;
  t: Translate;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [options, setOptions] = useState<ExtraDeliveryBoard | null>(null);
  const [reason, setReason] = useState('');
  const [supplier, setSupplier] = useState('');
  const [expectedAt, setExpectedAt] = useState('');
  const [note, setNote] = useState('');
  const [quantities, setQuantities] = useState<Record<number, string>>({});
  const [picked, setPicked] = useState<Record<number, boolean>>({});
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (order === null) {
      return;
    }

    setReason('');
    setSupplier('');
    setExpectedAt('');
    setNote('');
    setPicked({});
    setQuantities(
      Object.fromEntries(
        order.fittings.map((line) => [line.product_id, String(line.quantity)]),
      ),
    );

    // Powody i dostawcy z tablicy dostaw — jedno zrodlo list wyboru.
    void (async () => {
      const { content } = await WarehouseApi.extraDeliveries('open');
      setOptions(content?.data ?? null);
    })();
  }, [order?.id]);

  const items = (order?.fittings ?? [])
    .filter((line) => picked[line.product_id])
    .map((line) => ({
      product_id: line.product_id,
      quantity: Number((quantities[line.product_id] ?? '').replace(',', '.')),
    }))
    .filter((line) => Number.isFinite(line.quantity) && line.quantity > 0);

  const save = async () => {
    if (order === null) {
      return;
    }

    setSaving(true);

    const { content, response } = await WarehouseApi.createExtraDelivery({
      order_id: order.id,
      supplier_id: supplier === '' ? null : Number(supplier),
      reason,
      expected_at: expectedAt === '' ? null : expectedAt,
      note: note.trim() === '' ? null : note.trim(),
      items,
    });

    setSaving(false);

    if (!response.success) {
      const errors = content?.data ?? content?.errors ?? {};
      const first = Object.values(errors).find(
        (value): value is string[] =>
          Array.isArray(value) && typeof value[0] === 'string',
      );
      notifyError(first?.[0] ?? t('api.ise'));

      return;
    }

    notifySuccess(t('page.warehouse.extra.created'));
    onSaved();
  };

  return (
    <Drawer
      open={order !== null}
      onClose={onClose}
      narrow
      kicker={t('page.warehouse.tab_extra')}
      title={t('page.warehouse.extra.new_for', { number: order?.number ?? '' })}
      foot={
        <div className="ge-drawer__foot-end">
          <Button variant="text" onClick={onClose}>
            {t('cancel')}
          </Button>
          <Button
            variant="contained"
            loading={saving}
            disabled={reason === '' || items.length === 0}
            onClick={() => void save()}
          >
            {t('page.warehouse.extra.create')}
          </Button>
        </div>
      }
    >
      <DrawerColumn>
        <Fieldset tone="ident" label={t('page.warehouse.extra.why')}>
          <label className="ge-uf">
            <span className="ge-uf__label">
              {t('page.warehouse.extra.column.reason')}
              <span className="ge-uf__req"> •</span>
            </span>
            <select
              className={
                reason === ''
                  ? 'ge-uf__input ge-uf__select is-empty'
                  : 'ge-uf__input ge-uf__select'
              }
              value={reason}
              onChange={(event) => setReason(event.target.value)}
            >
              <option value="">{t('page.warehouse.extra.reason_pick')}</option>
              {(options?.reasons ?? []).map((row) => (
                <option key={row.value} value={row.value}>
                  {row.label}
                </option>
              ))}
            </select>
          </label>
          <label className="ge-uf">
            <span className="ge-uf__label">{t('page.warehouse.supplier')}</span>
            <select
              className="ge-uf__input ge-uf__select"
              value={supplier}
              onChange={(event) => setSupplier(event.target.value)}
            >
              <option value="">
                {t('page.warehouse.extra.supplier_none')}
              </option>
              {(options?.suppliers ?? []).map((row) => (
                <option key={row.id} value={row.id}>
                  {row.name}
                </option>
              ))}
            </select>
          </label>
          <label className="ge-uf">
            <span className="ge-uf__label">
              {t('page.warehouse.expected_at')}
            </span>
            <input
              type="date"
              className="ge-uf__input"
              value={expectedAt}
              onChange={(event) => setExpectedAt(event.target.value)}
            />
          </label>
          <label className="ge-uf">
            <span className="ge-uf__label">{t('page.warehouse.note')}</span>
            <input
              className="ge-uf__input"
              maxLength={500}
              value={note}
              onChange={(event) => setNote(event.target.value)}
            />
          </label>
        </Fieldset>

        <Fieldset tone="terms" label={t('page.warehouse.extra.what')}>
          {(order?.fittings ?? []).map((line) => (
            <div className="ge-extra__line" key={line.product_id}>
              <label className="ge-pick">
                <input
                  type="checkbox"
                  checked={picked[line.product_id] ?? false}
                  onChange={(event) =>
                    setPicked((current) => ({
                      ...current,
                      [line.product_id]: event.target.checked,
                    }))
                  }
                />
                {line.name}
              </label>
              <input
                className="ge-uf__input r"
                inputMode="decimal"
                aria-label={t('page.warehouse.column.needed')}
                value={quantities[line.product_id] ?? ''}
                onChange={(event) =>
                  setQuantities((current) => ({
                    ...current,
                    [line.product_id]: event.target.value,
                  }))
                }
              />
            </div>
          ))}
          <FieldNote>{t('page.warehouse.extra.stock_note')}</FieldNote>
        </Fieldset>
      </DrawerColumn>
    </Drawer>
  );
}
