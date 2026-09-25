import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { Form } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import { OrdersApi } from '@app/api/OrdersApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Field, { Choice, Toggle } from '@app/components/drawer/Field';
import Fieldset, { FieldNote } from '@app/components/drawer/Fieldset';

type Props = {
  orderId: number;
  invoice: {
    type_id: number | null;
    buyer_name: string | null;
    buyer_tax_id: string | null;
    buyer_address: string | null;
    accounting_note: string | null;
  };
  types: { id: number; name: string; vat_rate: number }[];
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
};

/**
 * Dane do faktury: typ (stawka VAT) i nabywca.
 *
 * Bez typu faktury nie ma kwoty brutto — a z nią limitu kupieckiego,
 * procentu wpłat i przejścia do produkcji. Dotąd dało się go wybrać
 * tylko przy zakładaniu zlecenia.
 *
 * „Dane jak kontrahent" to brak własnego nabywcy, nie kopia kartoteki:
 * kopia rozjechałaby się przy pierwszej zmianie adresu kontrahenta.
 */
export default function InvoiceDrawer({
  orderId,
  invoice,
  types,
  open,
  onClose,
  onSaved,
}: Props) {
  const t = useTranslation();
  const form = useForm();
  const [saving, setSaving] = useState(false);
  const same = form.watch('buyer_same');

  useEffect(() => {
    if (!open) {
      return;
    }

    form.reset({
      invoice_type_id: invoice.type_id === null ? '' : String(invoice.type_id),
      buyer_same: invoice.buyer_name === null,
      buyer_name: invoice.buyer_name ?? '',
      buyer_tax_id: invoice.buyer_tax_id ?? '',
      buyer_address: invoice.buyer_address ?? '',
      accounting_note: invoice.accounting_note ?? '',
    });
  }, [open, invoice]);

  const submit = async (data: any) => {
    setSaving(true);

    const { content, response } = await OrdersApi.saveInvoice(orderId, {
      invoice_type_id: data.invoice_type_id,
      buyer_same: Boolean(data.buyer_same),
      buyer_name: data.buyer_name ?? '',
      buyer_tax_id: data.buyer_tax_id ?? '',
      buyer_address: data.buyer_address ?? '',
      accounting_note: data.accounting_note ?? '',
    });

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
      kicker={t('page.orders.invoice.kicker')}
      title={t('page.orders.invoice.title')}
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
          <Fieldset tone="terms" label={t('page.orders.invoice.type')}>
            <Choice
              name="invoice_type_id"
              label={t('page.orders.invoice.type')}
              required
              emptyLabel={t('page.orders.invoice.type_pick')}
              options={types.map((type) => ({
                value: type.id,
                label: `${type.name} · ${type.vat_rate} %`,
              }))}
            />
            <FieldNote>{t('page.orders.invoice.type_note')}</FieldNote>
          </Fieldset>

          <Fieldset tone="contact" label={t('page.orders.invoice.buyer')}>
            <Toggle
              name="buyer_same"
              label={t('page.orders.invoice.buyer_same')}
            />
            {!same && (
              <>
                <Field
                  name="buyer_name"
                  label={t('page.orders.invoice.buyer_name')}
                  required
                />
                <Field
                  name="buyer_tax_id"
                  label={t('page.orders.invoice.buyer_tax_id')}
                />
                <Field
                  name="buyer_address"
                  label={t('page.orders.invoice.buyer_address')}
                />
              </>
            )}
            <Field
              name="accounting_note"
              label={t('page.orders.invoice.accounting_note')}
            />
          </Fieldset>
        </DrawerColumn>
      </Form>
    </Drawer>
  );
}
