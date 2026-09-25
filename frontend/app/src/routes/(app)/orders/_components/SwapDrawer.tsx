import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type { OrderItemsBoard, SwapPreview } from '@app/api/OrdersApi';
import { OrdersApi } from '@app/api/OrdersApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Fieldset, { FieldNote } from '@app/components/drawer/Fieldset';

type Props = {
  orderId: number;
  board: OrderItemsBoard;
  itemIds: number[];
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
};

/**
 * „Zamień wszystko" — inny materiał w zaznaczonych formatkach.
 *
 * Najpierw podgląd, potem zapis: zamiana wycenia etapy na nowo
 * z cennika, więc ręcznie wpisane stawki przepadają, a przy kilku
 * pasujących pozycjach etap czeka na wybór. Człowiek ma to zobaczyć,
 * zanim kliknie, a nie po tym, jak suma zlecenia się zmieni.
 */
export default function SwapDrawer({
  orderId,
  board,
  itemIds,
  open,
  onClose,
  onSaved,
}: Props) {
  const t = useTranslation();
  const [productId, setProductId] = useState('');
  const [preview, setPreview] = useState<SwapPreview | null>(null);
  const [problem, setProblem] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (open) {
      setProductId('');
      setPreview(null);
      setProblem(null);
    }
  }, [open]);

  useEffect(() => {
    if (!open || productId === '') {
      setPreview(null);

      return;
    }

    let current = true;

    void (async () => {
      const { content, response } = await OrdersApi.previewSwap(orderId, {
        product_id: productId,
        item_ids: itemIds,
      });

      if (!current) {
        return;
      }

      if (!response.success) {
        setPreview(null);
        setProblem(firstError(content) ?? t('api.ise'));

        return;
      }

      setProblem(null);
      setPreview(content?.data ?? null);
    })();

    // Szybka zmiana materialu: odpowiedz dla poprzedniego wyboru nie
    // moze nadpisac podgladu dla obecnego.
    return () => {
      current = false;
    };
  }, [open, productId, itemIds.join(',')]);

  const apply = async () => {
    setSaving(true);

    const { content, response } = await OrdersApi.swapMaterial(orderId, {
      product_id: productId,
      item_ids: itemIds,
    });

    setSaving(false);

    if (!response.success) {
      notifyError(firstError(content) ?? t('api.ise'));

      return;
    }

    notifySuccess(
      t('page.orders.swap.done', { count: content?.data?.changed ?? 0 }),
    );
    onSaved();
  };

  const changed = preview?.rows.filter((row) => !row.unchanged).length ?? 0;

  return (
    <Drawer
      open={open}
      onClose={onClose}
      narrow
      kicker={t('page.orders.panes.title')}
      title={t('page.orders.swap.title', { count: itemIds.length })}
      foot={
        <div className="ge-drawer__foot-end">
          <Button variant="text" onClick={onClose}>
            {t('cancel')}
          </Button>
          <Button
            variant="contained"
            loading={saving}
            disabled={preview === null || changed === 0}
            onClick={() => void apply()}
          >
            {t('page.orders.swap.apply', { count: changed })}
          </Button>
        </div>
      }
    >
      <DrawerColumn>
        <Fieldset tone="ident" label={t('page.orders.swap.material')}>
          <label className="ge-uf">
            <span className="ge-uf__label">
              {t('page.orders.swap.new_material')}
              <span className="ge-uf__req"> •</span>
            </span>
            <select
              className={
                productId === ''
                  ? 'ge-uf__input ge-uf__select is-empty'
                  : 'ge-uf__input ge-uf__select'
              }
              value={productId}
              onChange={(event) => setProductId(event.target.value)}
            >
              <option value="">{t('page.orders.panes.material_pick')}</option>
              {board.catalogue.products.map((product) => (
                <option key={product.id} value={product.id}>
                  {product.group
                    ? `${product.group} · ${product.name}`
                    : product.name}
                </option>
              ))}
            </select>
          </label>
          <FieldNote>{t('page.orders.swap.note')}</FieldNote>
        </Fieldset>

        {problem !== null && (
          <div className="ge-note ge-note--warn">{problem}</div>
        )}

        {preview !== null && (
          <Fieldset tone="terms" label={t('page.orders.swap.preview')}>
            <div className="ge-calc">
              {preview.rows.map((row) => (
                <div
                  key={row.id}
                  className={
                    row.unchanged
                      ? 'ge-calc__row ge-calc__row--off'
                      : 'ge-calc__row'
                  }
                >
                  <span>{row.name}</span>
                  <span className="ge-calc__formula">
                    {row.unchanged
                      ? t('page.orders.swap.unchanged')
                      : row.pending > 0
                        ? t('page.orders.swap.row_pending', {
                            count: row.pending,
                          })
                        : `${row.before} →`}
                  </span>
                  <span className="ge-calc__amount">{row.after}</span>
                </div>
              ))}
              <div className="ge-calc__row ge-calc__row--total">
                <span>{t('page.orders.swap.total')}</span>
                <span className="ge-calc__formula">{preview.before} →</span>
                <span className="ge-calc__amount">{preview.after}</span>
              </div>
            </div>

            {preview.pending > 0 && (
              <div className="ge-note ge-note--warn">
                {t('page.orders.swap.pending', { count: preview.pending })}
              </div>
            )}
            {preview.glass_missing && (
              <div className="ge-note ge-note--warn">
                {t('page.orders.swap.glass_missing')}
              </div>
            )}
          </Fieldset>
        )}
      </DrawerColumn>
    </Drawer>
  );
}

function firstError(content: any): string | null {
  const errors = content?.data ?? content?.errors;

  if (errors === null || typeof errors !== 'object') {
    return null;
  }

  for (const value of Object.values(errors)) {
    if (Array.isArray(value) && typeof value[0] === 'string') {
      return value[0];
    }
  }

  return null;
}
