import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { Form } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type { InvestmentVat } from '@app/api/OrdersApi';
import { OrdersApi } from '@app/api/OrdersApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Field, { Choice } from '@app/components/drawer/Field';
import Fieldset, { FieldNote } from '@app/components/drawer/Fieldset';

type Props = {
  orderId: number;
  investment: InvestmentVat | null;
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
};

/**
 * Inwestycja mieszkaniowa — dwie dane, od których zależy stawka VAT.
 *
 * Rodzaj obiektu wybiera limit powierzchni (art. 41 ust. 12b ustawy
 * o VAT), powierzchnia mówi, czy limit jest przekroczony. Po
 * przekroczeniu stawka obniżona nie znika — obejmuje tę część kwoty,
 * która odpowiada udziałowi metrażu mieszczącego się w limicie.
 *
 * Metraż bez rodzaju obiektu nie znaczy nic, bo limit bierze się
 * właśnie z rodzaju — dlatego pole gaśnie, gdy rodzaju nie ma.
 */
export default function InvestmentDrawer({
  orderId,
  investment,
  open,
  onClose,
  onSaved,
}: Props) {
  const t = useTranslation();
  const form = useForm();
  const [saving, setSaving] = useState(false);
  const [type, setType] = useState<string>('');

  useEffect(() => {
    if (!open) {
      return;
    }

    setType(investment?.type ?? '');
    form.reset({
      investment_type: investment?.type ?? '',
      investment_area_m2:
        investment?.area_m2 === null || investment?.area_m2 === undefined
          ? ''
          : String(investment.area_m2),
    });
  }, [open, investment?.type, investment?.area_m2]);

  const submit = async (data: any) => {
    setSaving(true);

    const { content, response } = await OrdersApi.saveInvestment(orderId, {
      investment_type: data.investment_type || null,
      investment_area_m2: data.investment_area_m2 || null,
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
      kicker={t('page.orders.investment.kicker')}
      title={t('page.orders.investment.title')}
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
          <Fieldset tone="terms" label={t('page.orders.investment.what')}>
            <Choice
              name="investment_type"
              label={t('page.orders.investment.type')}
              onChange={(value: string) => setType(value)}
              options={[
                { value: '', label: t('page.orders.investment.type_none') },
                { value: 'house', label: t('page.orders.investment.house') },
                { value: 'flat', label: t('page.orders.investment.flat') },
              ]}
            />
            <FieldNote>{t('page.orders.investment.type_note')}</FieldNote>

            {type !== '' && (
              <>
                <Field
                  name="investment_area_m2"
                  label={t('page.orders.investment.area')}
                  placeholder={t('page.orders.investment.area_hint')}
                />
                <FieldNote>{t('page.orders.investment.area_note')}</FieldNote>
              </>
            )}
          </Fieldset>
        </DrawerColumn>
      </Form>
    </Drawer>
  );
}
