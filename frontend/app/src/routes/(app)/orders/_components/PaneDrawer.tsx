import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { Form } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type { OrderItemsBoard, OrderPaneRow } from '@app/api/OrdersApi';
import { OrdersApi } from '@app/api/OrdersApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Field, { Choice, Toggle } from '@app/components/drawer/Field';
import Fieldset, { FieldNote, FieldRow } from '@app/components/drawer/Fieldset';

type Props = {
  orderId: number;
  board: OrderItemsBoard;
  item: OrderPaneRow | null;
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
};

const empty = {
  order_list_id: '',
  product_id: '',
  width_mm: '',
  height_mm: '',
  quantity: 1,
  is_irregular_shape: false,
  is_tempered: false,
  needs_mark: false,
};

/**
 * Formatka: materiał, wymiary i procesy.
 *
 * Cena nie jest polem formularza — liczy ją serwer przy zapisie i
 * zapisuje razem ze ścieżką wyliczenia. Gdyby dało się ją tu wpisać,
 * ścieżka przestałaby cokolwiek znaczyć.
 */
export default function PaneDrawer({
  orderId,
  board,
  item,
  open,
  onClose,
  onSaved,
}: Props) {
  const t = useTranslation();
  const form = useForm();
  const [processes, setProcesses] = useState<number[]>([]);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!open) {
      return;
    }

    setProcesses(item?.processes.map((entry) => entry.process_id) ?? []);

    form.reset({
      ...empty,
      order_list_id: board.lists[0]?.id ?? '',
      product_id: item?.product_id ?? '',
      width_mm: item?.width_mm ?? '',
      height_mm: item?.height_mm ?? '',
      quantity: Number(item?.quantity ?? 1),
      is_irregular_shape: item?.is_irregular_shape ?? false,
      is_tempered: item?.is_tempered ?? false,
      needs_mark: item?.needs_mark ?? false,
    });
  }, [open, item?.id]);

  const toggle = (id: number) =>
    setProcesses((current) =>
      current.includes(id)
        ? current.filter((value) => value !== id)
        : [...current, id],
    );

  const submit = async (data: any) => {
    setSaving(true);

    const { content, response } = await OrdersApi.savePane(
      orderId,
      { ...data, processes },
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

  return (
    <Drawer
      open={open}
      onClose={onClose}
      narrow
      kicker={t('page.orders.panes.title')}
      title={t(item ? 'page.orders.panes.edit' : 'page.orders.panes.add')}
      foot={
        <>
          <span className="ge-drawer__foot-note">
            {t('page.orders.panes.price_note')}
          </span>
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
        </>
      }
    >
      <Form form={form} onSubmit={submit}>
        <DrawerColumn>
          <Fieldset
            tone="ident"
            label={t('page.orders.panes.section.material')}
          >
            <Choice
              name="product_id"
              label={t('page.orders.panes.material')}
              required
              emptyLabel={t('page.orders.panes.material_pick')}
              options={board.catalogue.products.map((product) => ({
                value: product.id,
                label: product.group
                  ? `${product.group} · ${product.name}`
                  : product.name,
              }))}
            />

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

          <Fieldset tone="addr" label={t('page.orders.panes.section.size')}>
            <FieldRow columns="1fr 1fr 1fr">
              <Field
                name="width_mm"
                label={t('page.orders.panes.width')}
                required
                emphasis="num"
                type="number"
              />
              <Field
                name="height_mm"
                label={t('page.orders.panes.height')}
                required
                emphasis="num"
                type="number"
              />
              <Field
                name="quantity"
                label={t('page.orders.panes.quantity')}
                emphasis="num"
                type="number"
              />
            </FieldRow>

            <FieldRow columns="1fr" paddingTop={10}>
              <Toggle
                name="is_tempered"
                label={t('page.orders.panes.tempered')}
              />
              <Toggle
                name="is_irregular_shape"
                label={t('page.orders.panes.irregular')}
              />
              <Toggle name="needs_mark" label={t('page.orders.panes.mark')} />
            </FieldRow>

            <FieldNote>{t('page.orders.panes.size_note')}</FieldNote>
          </Fieldset>

          <Fieldset
            tone="terms"
            label={t('page.orders.panes.section.processes')}
          >
            <div className="ge-chips">
              {board.catalogue.processes.map((process) => (
                <button
                  key={process.id}
                  type="button"
                  className={
                    processes.includes(process.id) ? 'ge-chip is-on' : 'ge-chip'
                  }
                  onClick={() => toggle(process.id)}
                >
                  {process.name}
                </button>
              ))}
            </div>
            <FieldNote>{t('page.orders.panes.processes_note')}</FieldNote>
          </Fieldset>

          {item && item.price_path.length > 0 && (
            <Fieldset
              tone="contact"
              label={t('page.orders.panes.section.path')}
            >
              {item.price_path.map((step, index) => (
                <div className="ge-kv" key={index}>
                  <span className="ge-kv__k">
                    {step.label}
                    {step.detail && (
                      <span className="ge-quiet"> — {step.detail}</span>
                    )}
                  </span>
                  <span>{step.value}</span>
                </div>
              ))}
            </Fieldset>
          )}
        </DrawerColumn>
      </Form>
    </Drawer>
  );
}
