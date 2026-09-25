import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { Form } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import { OrdersApi } from '@app/api/OrdersApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Field from '@app/components/drawer/Field';
import Fieldset, { FieldNote, FieldRow } from '@app/components/drawer/Fieldset';

type Props = {
  orderId: number;
  deadline: {
    client: string | null;
    shifted: string | null;
    shift_reason: string | null;
  };
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
};

/**
 * Termin zlecenia: klienta, przesunięty i powód przesunięcia.
 *
 * Oba terminy są edytowalne zawsze (decyzja Marcina). Przesunięty wygrywa
 * na liście i w pasmach pilności, a termin klienta zostaje obok do
 * porównania — dlatego to dwa pola, a nie jedno nadpisywane.
 */
export default function DeadlineDrawer({
  orderId,
  deadline,
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
      client_deadline: deadline.client ?? '',
      shifted_deadline: deadline.shifted ?? '',
      shift_reason: deadline.shift_reason ?? '',
    });
  }, [open, deadline.client, deadline.shifted, deadline.shift_reason]);

  const submit = async (data: any) => {
    setSaving(true);

    const { content, response } = await OrdersApi.saveDeadline(orderId, {
      client_deadline: data.client_deadline ?? '',
      shifted_deadline: data.shifted_deadline ?? '',
      shift_reason: data.shift_reason ?? '',
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
      kicker={t('page.orders.deadline.kicker')}
      title={t('page.orders.deadline.title')}
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
          <Fieldset tone="terms" label={t('page.orders.deadline.dates')}>
            <FieldRow columns="1fr 1fr">
              <Field
                name="client_deadline"
                label={t('page.orders.deadline.client')}
                type="date"
              />
              <Field
                name="shifted_deadline"
                label={t('page.orders.deadline.shifted')}
                type="date"
              />
            </FieldRow>
            <Field
              name="shift_reason"
              label={t('page.orders.deadline.reason')}
              placeholder={t('page.orders.deadline.reason_hint')}
            />
            <FieldNote>{t('page.orders.deadline.note')}</FieldNote>
            <FieldNote>{t('page.orders.deadline.note_auto')}</FieldNote>
          </Fieldset>
        </DrawerColumn>
      </Form>
    </Drawer>
  );
}
