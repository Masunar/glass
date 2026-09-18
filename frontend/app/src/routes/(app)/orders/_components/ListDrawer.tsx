import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { Form } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type { OrderItemsList } from '@app/api/OrdersApi';
import { OrdersApi } from '@app/api/OrdersApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Field, { Choice, Toggle } from '@app/components/drawer/Field';
import Fieldset, { FieldNote } from '@app/components/drawer/Fieldset';

type Props = {
  orderId: number;
  list: OrderItemsList | null;
  /** Czy da się usunąć — ostatniej listy i listy z pozycjami nie kasujemy. */
  removable: boolean;
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
};

/**
 * Lista zlecenia.
 *
 * Dwa przełączniki, które łatwo pomylić, a znaczą co innego.
 * **Nie wchodzi do kwoty** — lista nie należy do zlecenia: odrzucony
 * wariant, kwota zero. **Wstrzymana** — należy i jest wyceniona, ale
 * nie pójdzie na produkcję.
 */
export default function ListDrawer({
  orderId,
  list,
  removable,
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
      name: list?.name ?? '',
      role: list?.role ?? 'component',
      comment: list?.comment ?? '',
      is_included: list?.is_included ?? true,
      is_on_hold: list?.is_on_hold ?? false,
    });
  }, [open, list?.id]);

  const submit = async (data: any) => {
    setSaving(true);

    const { content, response } = await OrdersApi.saveList(
      orderId,
      data,
      list?.id,
    );

    setSaving(false);

    if (!validationCompleted(content, form.setError, t)) {
      return;
    }

    if (!response.success) {
      notifyError(content?.errors?.list?.[0] ?? t('api.ise'));

      return;
    }

    notifySuccess(t('api.save_success'));
    onSaved();
  };

  const remove = async () => {
    if (list === null) {
      return;
    }

    setSaving(true);
    const { content, response } = await OrdersApi.deleteList(orderId, list.id);
    setSaving(false);

    if (!response.success) {
      notifyError(content?.errors?.list?.[0] ?? t('api.ise'));

      return;
    }

    onSaved();
  };

  return (
    <Drawer
      open={open}
      onClose={onClose}
      narrow
      kicker={t('page.orders.lists.kicker')}
      title={t(list ? 'page.orders.lists.edit' : 'page.orders.lists.add')}
      foot={
        <>
          {list !== null && removable && (
            <Button variant="text" onClick={() => void remove()}>
              {t('delete')}
            </Button>
          )}
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
          <Fieldset tone="terms" label={t('page.orders.lists.section.what')}>
            <Field
              name="name"
              label={t('page.orders.lists.name')}
              placeholder={t('page.orders.lists.name_hint')}
            />
            <Choice
              name="role"
              label={t('page.orders.lists.role')}
              options={[
                {
                  value: 'component',
                  label: t('page.orders.card.role_component'),
                },
                {
                  value: 'alternative',
                  label: t('page.orders.card.role_alternative'),
                },
              ]}
            />
            <FieldNote>{t('page.orders.lists.role_note')}</FieldNote>
          </Fieldset>

          <Fieldset tone="addr" label={t('page.orders.lists.section.state')}>
            {/* Dwie rozne rzeczy, ktore latwo pomylic — stad osobne
                zdanie przy kazdym przelaczniku, a nie jedno na dole. */}
            <Toggle
              name="is_included"
              label={t('page.orders.lists.included')}
            />
            <FieldNote>{t('page.orders.lists.included_note')}</FieldNote>
            <Toggle name="is_on_hold" label={t('page.orders.lists.on_hold')} />
            <FieldNote>{t('page.orders.lists.on_hold_note')}</FieldNote>
          </Fieldset>

          <Fieldset tone="contact" label={t('page.orders.lists.section.note')}>
            <Field
              name="comment"
              label={t('page.orders.lists.comment')}
              placeholder={t('page.orders.lists.comment_hint')}
            />
            <FieldNote>{t('page.orders.lists.comment_note')}</FieldNote>
          </Fieldset>
        </DrawerColumn>
      </Form>
    </Drawer>
  );
}
