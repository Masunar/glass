import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { Form } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import { OrdersApi } from '@app/api/OrdersApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import { Choice } from '@app/components/drawer/Field';
import Fieldset, { FieldNote } from '@app/components/drawer/Fieldset';

type Props = {
  orderId: number;
  ownerId: number | null;
  owners: { id: number; name: string }[];
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
};

/**
 * Przekazanie zlecenia innej osobie.
 *
 * Prowadzący mówi, **kogo pytać o to zlecenie dzisiaj** — nie kto je
 * założył i nie kto może je oglądać. Widzą je wszyscy, tak jak
 * wcześniej; zmienia się tylko odpowiedzialność, inicjały na liście
 * i to, czyj pulpit podbija tę sprawę na górę.
 *
 * Lista to konta z dostępem do zleceń. Wskazanie kogoś, kto zleceń nie
 * widzi, nie byłoby przekazaniem, tylko cichym zgubieniem sprawy.
 */
export default function OwnerDrawer({
  orderId,
  ownerId,
  owners,
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

    form.reset({ owner_id: ownerId === null ? '' : String(ownerId) });
  }, [open, ownerId]);

  const submit = async (data: any) => {
    setSaving(true);

    const { content, response } = await OrdersApi.changeOwner(
      orderId,
      Number(data.owner_id),
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
      kicker={t('page.orders.owner.kicker')}
      title={t('page.orders.owner.title')}
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
          <Fieldset tone="contact" label={t('page.orders.owner.who')}>
            <Choice
              name="owner_id"
              label={t('page.orders.owner.person')}
              required
              emptyLabel={t('page.orders.owner.none')}
              options={owners.map((person) => ({
                value: person.id,
                label: person.name,
              }))}
            />
            <FieldNote>{t('page.orders.owner.note')}</FieldNote>
          </Fieldset>
        </DrawerColumn>
      </Form>
    </Drawer>
  );
}
