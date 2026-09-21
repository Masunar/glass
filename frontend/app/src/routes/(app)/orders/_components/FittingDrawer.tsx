import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { Form } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type { OrderFittingRow, OrderItemsBoard } from '@app/api/OrdersApi';
import { OrdersApi } from '@app/api/OrdersApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Field, { Choice } from '@app/components/drawer/Field';
import Fieldset, { FieldNote, FieldRow } from '@app/components/drawer/Fieldset';

type Props = {
  orderId: number;
  board: OrderItemsBoard;
  item: OrderFittingRow | null;
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
};

/**
 * Okucie na zleceniu.
 *
 * Inaczej niż przy usłudze, **cenę liczy cennik**. Okucie jest pozycją
 * katalogową z ceną zakupu i współczynnikiem, więc idzie tą samą drogą
 * co szkło. Pole ceny jest nadpisaniem, nie źródłem: puste znaczy
 * „weź z cennika", a zero znaczy zero — okucie dorzucone gratis to
 * decyzja handlowa, nie brak danych.
 */
export default function FittingDrawer({
  orderId,
  board,
  item,
  open,
  onClose,
  onSaved,
}: Props) {
  const t = useTranslation();
  const form = useForm();
  const [saving, setSaving] = useState(false);
  const [fromSet, setFromSet] = useState(false);

  useEffect(() => {
    if (!open) {
      return;
    }

    setFromSet(false);

    form.reset({
      order_list_id: board.lists[0]?.id ?? '',
      product_id: item?.product_id ?? '',
      fitting_set_id: '',
      quantity: item ? Number(item.quantity) : 1,
      unit_net_price: '',
      note: item?.note ?? '',
    });
  }, [open, item?.id]);

  const submit = async (data: any) => {
    setSaving(true);

    const { content, response } = fromSet
      ? await OrdersApi.addFittingSet(orderId, {
          fitting_set_id: data.fitting_set_id,
          order_list_id: data.order_list_id,
        })
      : await OrdersApi.saveFitting(
          orderId,
          {
            ...data,
            // Puste pole znaczy „z cennika". Gdyby poleciało jako pusty
            // napis, walidacja `nullable` i tak by go nie przepuściła.
            unit_net_price:
              data.unit_net_price === '' ? null : data.unit_net_price,
            note: data.note === '' ? null : data.note,
          },
          item?.id,
        );

    setSaving(false);

    if (!validationCompleted(content, form.setError, t)) {
      return;
    }

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    notifySuccess(t('api.save_success'));
    onSaved();
  };

  const label = (product: {
    name: string;
    code: string | null;
    finish: string | null;
  }) =>
    [product.code, product.name, product.finish].filter(Boolean).join(' · ');

  return (
    <Drawer
      open={open}
      onClose={onClose}
      narrow
      kicker={t('page.orders.panes.title')}
      title={t(
        item
          ? 'page.orders.panes.fitting_edit'
          : 'page.orders.panes.fitting_add',
      )}
      foot={
        <div className="ge-drawer__foot-end">
          <Button variant="text" onClick={onClose}>
            {t('cancel')}
          </Button>
          <Button
            variant="contained"
            loading={saving}
            onClick={() => void form.handleSubmit(submit)()}
          >
            {t('save')}
          </Button>
        </div>
      }
    >
      <Form form={form} onSubmit={submit}>
        <DrawerColumn>
          <Fieldset tone="ident" label={t('page.orders.panes.section.fitting')}>
            {/* Zestaw dokłada kilka pozycji naraz i znika — na zleceniu
                zostają zwykłe okucia. Przełącznik, a nie osobny panel,
                bo to ta sama czynność: „dopisz okucia do listy". */}
            {!item && board.catalogue.sets.length > 0 && (
              <div className="ge-switch">
                <button
                  type="button"
                  className={fromSet ? 'ge-switch__tab' : 'ge-switch__tab is-active'}
                  onClick={() => setFromSet(false)}
                >
                  {t('page.orders.panes.fitting_single')}
                </button>
                <button
                  type="button"
                  className={fromSet ? 'ge-switch__tab is-active' : 'ge-switch__tab'}
                  onClick={() => setFromSet(true)}
                >
                  {t('page.orders.panes.fitting_from_set')}
                </button>
              </div>
            )}

            {fromSet ? (
              <>
                <Choice
                  name="fitting_set_id"
                  label={t('page.orders.panes.set')}
                  required
                  emptyLabel={t('page.orders.panes.set_pick')}
                  options={board.catalogue.sets.map((set) => ({
                    value: set.id,
                    label: t('page.orders.panes.set_option', {
                      name: set.name,
                      count: set.items,
                    }),
                  }))}
                />
                <FieldNote>{t('page.orders.panes.set_note')}</FieldNote>
              </>
            ) : (
            <>
            <Choice
              name="product_id"
              label={t('page.orders.panes.fitting')}
              required
              emptyLabel={t('page.orders.panes.fitting_pick')}
              options={board.catalogue.fittings.map((product) => ({
                value: product.id,
                label: label(product),
              }))}
            />

            <FieldRow columns="1fr 1fr" paddingTop={4}>
              <Field
                name="quantity"
                label={t('page.orders.panes.quantity')}
                required
                emphasis="num"
              />
              <Field
                name="unit_net_price"
                label={t('page.orders.panes.unit_price')}
                placeholder={t('page.orders.panes.step_price_hint')}
                emphasis="num"
              />
            </FieldRow>

            <FieldNote>{t('page.orders.panes.fitting_note')}</FieldNote>

            <Field name="note" label={t('page.orders.panes.note')} />
            </>
            )}

            {board.lists.length > 1 && (
              <Choice
                name="order_list_id"
                label={t('page.orders.panes.list')}
                options={board.lists.map((list) => ({
                  value: list.id,
                  label:
                    list.name ??
                    t('page.orders.card.list', { number: list.number }),
                }))}
              />
            )}
          </Fieldset>
        </DrawerColumn>
      </Form>
    </Drawer>
  );
}
