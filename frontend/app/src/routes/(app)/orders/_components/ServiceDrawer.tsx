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
import Field, { Choice } from '@app/components/drawer/Field';
import Fieldset, { FieldNote, FieldRow } from '@app/components/drawer/Fieldset';

type Props = {
  orderId: number;
  board: OrderItemsBoard;
  item: OrderPaneRow | null;
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
};

/**
 * Usługa na zleceniu: montaż, demontaż, transport.
 *
 * Cena jest tu wpisywana, a nie liczona — inaczej niż przy formatce.
 * Usługa nie ma wymiarów ani procesów, więc nie ma z czego jej wyliczyć;
 * cennik podpowiada nazwę, kwotę ustala handlowiec.
 */
export default function ServiceDrawer({
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

  useEffect(() => {
    if (!open) {
      return;
    }

    form.reset({
      order_list_id: board.lists[0]?.id ?? '',
      product_id: item?.product_id ?? '',
      name: item?.name ?? '',
      quantity: item ? Number(item.quantity) : 1,
      unit_net_price: item?.unit_net_price ?? '',
    });
  }, [open, item?.id]);

  const submit = async (data: any) => {
    setSaving(true);

    const { content, response } = await OrdersApi.saveService(
      orderId,
      data,
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
      title={t(
        item
          ? 'page.orders.panes.service_edit'
          : 'page.orders.panes.service_add',
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
          <Fieldset tone="ident" label={t('page.orders.panes.section.service')}>
            {board.catalogue.services.length > 0 && (
              <Choice
                name="product_id"
                label={t('page.orders.panes.from_catalogue')}
                emptyLabel={t('page.orders.panes.own_name')}
                options={board.catalogue.services.map((product) => ({
                  value: product.id,
                  label: product.name,
                }))}
                onChange={(value) => {
                  const picked = board.catalogue.services.find(
                    (product) => String(product.id) === value,
                  );

                  if (picked) {
                    form.setValue('name', picked.name, { shouldDirty: true });
                  }
                }}
              />
            )}

            <Field name="name" label={t('page.orders.panes.name')} required />

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
                required
                emphasis="num"
              />
            </FieldRow>

            <FieldNote>{t('page.orders.panes.service_note')}</FieldNote>

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
