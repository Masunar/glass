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
import Fieldset, { FieldNote } from '@app/components/drawer/Fieldset';

type Props = {
  orderId: number;
  exceedsBy: string | null;
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
};

/**
 * Zgoda administratora na produkcję mimo przekroczonego limitu.
 *
 * Powód jest obowiązkowy: po miesiącu ktoś zapyta, dlaczego limit nie
 * zadziałał, a „bo tak" zapisane w dzienniku niczego nie wyjaśnia.
 */
export default function CreditOverrideDrawer({
  orderId,
  exceedsBy,
  open,
  onClose,
  onSaved,
}: Props) {
  const t = useTranslation();
  const form = useForm();
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (open) {
      form.reset({ reason: '' });
    }
  }, [open]);

  const submit = async (data: any) => {
    setSaving(true);

    const { content, response } = await OrdersApi.grantCreditOverride(
      orderId,
      data.reason ?? '',
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
      kicker={t('page.orders.credit_override.kicker')}
      title={t('page.orders.credit_override.title')}
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
            {t('page.orders.credit_override.confirm')}
          </Button>
        </div>
      }
    >
      <Form form={form} onSubmit={submit}>
        <DrawerColumn>
          <Fieldset tone="terms" label={t('page.orders.credit_override.why')}>
            <Field
              name="reason"
              label={t('page.orders.credit_override.reason')}
              placeholder={t('page.orders.credit_override.reason_hint')}
              required
            />
            <FieldNote>
              {t('page.orders.credit_override.note', {
                amount: exceedsBy ?? '—',
              })}
            </FieldNote>
          </Fieldset>
        </DrawerColumn>
      </Form>
    </Drawer>
  );
}
