import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { Form } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import { CatalogApi } from '@app/api/CatalogApi';
import type { PriceGroup } from '@app/api/PriceListApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Field, { Toggle } from '@app/components/drawer/Field';
import Fieldset, { FieldNote, FieldRow } from '@app/components/drawer/Fieldset';

type Props = {
  section: string;
  /** `null` — nowa grupa; obiekt — edycja istniejącej. */
  group: PriceGroup | null;
  open: boolean;
  onClose: () => void;
  onSaved: (groupId: number) => void;
};

/**
 * Grupa asortymentowa — lewa kolumna cennika.
 *
 * Grupa nie niesie ceny ani współczynnika: porządkuje kartotekę, żeby
 * macierz dała się przejrzeć. Stąd jeden `Fieldset`, nie trzy.
 */
export default function GroupDrawer({
  section,
  group,
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
      name: group?.name ?? '',
      manufacturer: group?.manufacturer ?? '',
      series: group?.series ?? '',
      is_active: group?.is_active ?? true,
    });
  }, [open, group?.id]);

  const submit = async (data: any) => {
    setSaving(true);

    const { content, response } = await CatalogApi.saveGroup(
      { ...data, section },
      group?.id,
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
    onSaved(Number(content?.data?.id));
  };

  return (
    <Drawer
      narrow
      open={open}
      onClose={onClose}
      kicker={t('page.price_list.title')}
      title={t(
        group ? 'page.price_list.group.edit' : 'page.price_list.group.add',
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
          <Fieldset tone="ident" label={t('page.price_list.group.section')}>
            <FieldRow columns="1fr">
              <Field
                name="name"
                label={t('page.price_list.group.name')}
                required
              />
            </FieldRow>
            <FieldRow columns="1fr 1fr" paddingTop={12}>
              <Field
                name="manufacturer"
                label={t('page.price_list.group.manufacturer')}
              />
              <Field name="series" label={t('page.price_list.group.series')} />
            </FieldRow>
            <FieldNote>
              {t('page.price_list.group.manufacturer_hint')}
            </FieldNote>
            <div style={{ paddingTop: 12 }}>
              <Toggle name="is_active" label={t('page.price_list.active')} />
            </div>
            <FieldNote>{t('page.price_list.active_hint')}</FieldNote>
          </Fieldset>
        </DrawerColumn>
      </Form>
    </Drawer>
  );
}
