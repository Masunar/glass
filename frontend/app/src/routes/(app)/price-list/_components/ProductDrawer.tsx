import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { Form, FormControl } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import { CatalogApi } from '@app/api/CatalogApi';
import type { PriceRow } from '@app/api/PriceListApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Field, { Toggle } from '@app/components/drawer/Field';
import Fieldset, { FieldNote, FieldRow } from '@app/components/drawer/Fieldset';

type Props = {
  section: string;
  groupId: number | null;
  /** `null` — nowa pozycja w bieżącej grupie. */
  row: PriceRow | null;
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
};

const UNITS = [
  { value: 'm2', label: 'm²' },
  { value: 'mb', label: 'mb' },
  { value: 'pcs', label: 'szt.' },
];

const VAT_RATES = [23, 8, 5, 0].map((rate) => ({
  value: rate,
  label: `${rate}%`,
}));

/**
 * Pozycja kartoteki produktów.
 *
 * Pola różnią się sekcją, bo **kartoteka jest jedna z dyskryminatorem**,
 * a nie pięć równoległych: szkło ma grubość i wariant, okucia
 * wykończenie i wymiar, usługa grubość szkła, do której się dobiera.
 * Pokazywanie wszystkich pól wszędzie kazałoby zgadywać, które są
 * puste z braku danych, a które z braku sensu.
 */
export default function ProductDrawer({
  section,
  groupId,
  row,
  open,
  onClose,
  onSaved,
}: Props) {
  const t = useTranslation();
  const form = useForm();
  const [saving, setSaving] = useState(false);

  const isGlass = section === 'glass';
  const isFittings = section === 'fittings';
  const isServices = section === 'services';

  useEffect(() => {
    if (!open) {
      return;
    }

    form.reset({
      name: row?.name ?? '',
      code: row?.code ?? '',
      manufacturer_code: row?.manufacturer_code ?? '',
      unit: row?.unit ?? (isGlass ? 'm2' : isServices ? 'mb' : 'pcs'),
      vat_rate: row?.vat_rate ?? 23,
      purchase_net_price: row?.purchase_net_price ?? '',
      thickness_mm: row?.thickness_mm ?? '',
      variant: row?.variant ?? '',
      is_tempered_by_default: row?.is_tempered_by_default ?? false,
      finish: row?.finish ?? '',
      dimension: row?.dimension ?? '',
      glass_thickness_mm: row?.glass_thickness_mm ?? '',
      is_made_to_order: row?.is_made_to_order ?? false,
      is_active: row?.is_active ?? true,
    });
  }, [open, row?.product_id]);

  const submit = async (data: any) => {
    setSaving(true);

    const { content, response } = await CatalogApi.saveProduct(
      { ...data, product_group_id: groupId },
      row?.product_id,
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
      wideForm
      open={open}
      onClose={onClose}
      kicker={t('page.price_list.title')}
      title={t(
        row ? 'page.price_list.product.edit' : 'page.price_list.product.add',
      )}
      foot={
        <>
          <span className="ge-drawer__foot-note">
            {t('page.price_list.product.purchase_hint')}
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
          <Fieldset tone="ident" label={t('page.price_list.product.section')}>
            <FieldRow columns="1fr">
              <Field
                name="name"
                label={t('page.price_list.product.name')}
                required
              />
            </FieldRow>

            {isGlass && (
              <FieldRow columns="1fr 2fr" paddingTop={12}>
                <Field
                  name="thickness_mm"
                  label={t('page.price_list.product.thickness')}
                  emphasis="num"
                  required
                />
                <Field
                  name="variant"
                  label={t('page.price_list.product.variant')}
                />
              </FieldRow>
            )}

            {isFittings && (
              <FieldRow columns="1fr 1fr" paddingTop={12}>
                <Field
                  name="finish"
                  label={t('page.price_list.product.finish')}
                />
                <Field
                  name="dimension"
                  label={t('page.price_list.product.dimension')}
                />
              </FieldRow>
            )}

            {isServices && (
              <FieldRow columns="1fr" paddingTop={12}>
                <Field
                  name="glass_thickness_mm"
                  label={t('page.price_list.product.service_thickness')}
                  emphasis="num"
                />
              </FieldRow>
            )}

            {isGlass && (
              <FieldNote>{t('page.price_list.product.variant_hint')}</FieldNote>
            )}
            {isServices && (
              <FieldNote>
                {t('page.price_list.product.service_thickness_hint')}
              </FieldNote>
            )}

            <FieldRow columns="1fr 1fr" paddingTop={12}>
              <Field
                name="code"
                label={t('page.price_list.product.code')}
                emphasis="key"
              />
              <Field
                name="manufacturer_code"
                label={t('page.price_list.product.manufacturer_code')}
                emphasis="key"
              />
            </FieldRow>
          </Fieldset>

          <Fieldset tone="terms" label={t('page.price_list.product.money')}>
            <FieldRow columns="1fr 1fr 2fr">
              <FormControl
                variant="select"
                name="unit"
                label={t('page.price_list.product.unit')}
                options={UNITS}
              />
              <FormControl
                variant="select"
                name="vat_rate"
                label={t('page.price_list.product.vat')}
                options={VAT_RATES}
              />
              <Field
                name="purchase_net_price"
                label={t('page.price_list.product.purchase')}
                emphasis="num"
              />
            </FieldRow>
            {/* Brak ceny zakupu nie jest zerem — cennik pokaze przy tej
                pozycji ostrzezenie zamiast wyliczonej ceny. */}
            <FieldNote>{t('page.price_list.product.purchase_hint')}</FieldNote>

            <div style={{ display: 'flex', gap: 20, paddingTop: 12 }}>
              {isGlass && (
                <Toggle
                  name="is_tempered_by_default"
                  label={t('page.price_list.product.tempered')}
                />
              )}
              <Toggle
                name="is_made_to_order"
                label={t('page.price_list.product.made_to_order')}
              />
              <Toggle name="is_active" label={t('page.price_list.active')} />
            </div>
            <FieldNote>
              {t('page.price_list.product.made_to_order_hint')}
            </FieldNote>
          </Fieldset>
        </DrawerColumn>
      </Form>
    </Drawer>
  );
}
