import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { Form } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import {
  type ContractorCard,
  type ContractorRow,
  ContractorsApi,
} from '@app/api/ContractorsApi';
import { RegonApi } from '@app/api/RegonApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Field, { Toggle } from '@app/components/drawer/Field';
import Fieldset, { FieldNote, FieldRow } from '@app/components/drawer/Fieldset';

type Props = {
  card: ContractorCard | null;
  open: boolean;
  onClose: () => void;
  onSaved: (id: number, keepOpen: boolean) => void;
};

const emptyValues = {
  type: 'company',
  name: '',
  short_name: '',
  tax_id: '',
  registry_id: '',
  first_name: '',
  last_name: '',
  phone: '',
  email: '',
  website: '',
  payment_days: 0,
  credit_limit: '0.00',
  is_supplier: false,
  is_active: true,
  note: '',
  street: '',
  building_number: '',
  unit_number: '',
  postal_code: '',
  city: '',
};

export default function ContractorDrawer({
  card,
  open,
  onClose,
  onSaved,
}: Props) {
  const t = useTranslation();
  const form = useForm();
  const [type, setType] = useState<'company' | 'person'>('company');
  const [fetching, setFetching] = useState(false);
  const [saving, setSaving] = useState(false);
  const [registryNote, setRegistryNote] = useState<string | null>(null);
  const [duplicate, setDuplicate] = useState<ContractorRow | null>(null);

  const contractor = card?.contractor;
  const address = card?.addresses?.registered ?? null;
  const isCompany = type === 'company';

  useEffect(() => {
    if (!open) {
      return;
    }

    setType(contractor?.type ?? 'company');
    setRegistryNote(null);
    setDuplicate(null);

    form.reset({
      ...emptyValues,
      type: contractor?.type ?? 'company',
      name: contractor?.name ?? '',
      short_name: contractor?.short_name ?? '',
      tax_id: contractor?.tax_id ?? '',
      registry_id: contractor?.registry_id ?? '',
      first_name: contractor?.first_name ?? '',
      last_name: contractor?.last_name ?? '',
      phone: contractor?.phone ?? '',
      email: contractor?.email ?? '',
      website: contractor?.website ?? '',
      payment_days: contractor?.payment_days ?? 0,
      credit_limit: contractor?.credit_limit ?? '0.00',
      is_supplier: contractor?.is_supplier ?? false,
      is_active: contractor?.is_active ?? true,
      note: contractor?.note ?? '',
      street: address?.street ?? '',
      building_number: address?.building_number ?? '',
      unit_number: address?.unit_number ?? '',
      postal_code: address?.postal_code ?? '',
      city: address?.city ?? '',
    });
  }, [open, contractor?.id]);

  const chooseType = (next: 'company' | 'person') => {
    setType(next);
    form.setValue('type', next);
  };

  /**
   * Podpowiedź z rejestru GUS.
   *
   * Wypełnia wyłącznie pola puste. Raz poprawiony ręcznie adres nie ma
   * być nadpisany przez rejestr, w którym firma bywa zameldowana pod
   * adresem księgowej.
   */
  const fetchFromRegistry = async () => {
    const taxId = String(form.getValues('tax_id') ?? '').replace(/\D+/g, '');

    if (taxId.length !== 10) {
      notifyError(t('page.contractors.form.registry_needs_tax_id'));
      return;
    }

    setFetching(true);
    const { content, response } = await RegonApi.findByNip(taxId);
    setFetching(false);

    if (!response.success) {
      notifyError(t('api.ise'));
      return;
    }

    const data = content?.data ?? {};

    if (!data.company_name) {
      setRegistryNote(t('page.contractors.form.registry_not_found'));
      return;
    }

    const fill = (field: string, value: unknown) => {
      if (value === null || value === undefined || value === '') {
        return;
      }

      if (String(form.getValues(field) ?? '') !== '') {
        return;
      }

      form.setValue(field, String(value), { shouldDirty: true });
    };

    fill('name', data.company_name);
    fill('registry_id', data.regon);
    fill('street', data.street);
    fill('building_number', data.building_number);
    fill('unit_number', data.apartment_number);
    fill('postal_code', data.postcode);
    fill('city', data.city);

    setRegistryNote(
      t('page.contractors.form.registry_filled', {
        time: new Date().toLocaleTimeString('pl-PL', {
          hour: '2-digit',
          minute: '2-digit',
        }),
      }),
    );

    await checkDuplicate(taxId);
  };

  /**
   * Duplikaty są realnym problemem starej bazy: dwa rekordy tej samej
   * firmy rozbijają historię klienta na pół. Zapis i tak odrzuci ten sam
   * NIP, ale ostrzeżenie ma się pojawić, zanim ktoś wypełni cały panel.
   */
  const checkDuplicate = async (query: string) => {
    if (query.trim().length < 3 || contractor?.id) {
      setDuplicate(null);
      return;
    }

    const { content } = await ContractorsApi.search(query, true);
    const rows: ContractorRow[] = content?.data?.contractors ?? [];

    setDuplicate(rows[0] ?? null);
  };

  const submit = async (data: any, keepOpen: boolean) => {
    const { street, building_number, unit_number, postal_code, city, ...rest } =
      data;

    setSaving(true);

    const { content, response } = await ContractorsApi.save(
      {
        ...rest,
        type,
        address: { street, building_number, unit_number, postal_code, city },
      },
      contractor?.id,
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
    onSaved(Number(content?.data?.id ?? contractor?.id), keepOpen);

    if (keepOpen) {
      form.reset(emptyValues);
      setDuplicate(null);
      setRegistryNote(null);
    }
  };

  return (
    <Drawer
      open={open}
      onClose={onClose}
      kicker={t('page.contractors.title')}
      title={t(
        contractor ? 'page.contractors.form.edit' : 'page.contractors.form.add',
      )}
      headExtra={
        <div className="ge-switch">
          <button
            type="button"
            className={isCompany ? 'is-active' : ''}
            onClick={() => chooseType('company')}
          >
            {t('page.contractors.type.company')}
          </button>
          <button
            type="button"
            className={isCompany ? '' : 'is-active'}
            onClick={() => chooseType('person')}
          >
            {t('page.contractors.type.person')}
          </button>
        </div>
      }
      banner={
        duplicate && (
          <div className="ge-dupe">
            <strong>{t('page.contractors.form.duplicate')}</strong> —{' '}
            {duplicate.display_name}
            {duplicate.tax_id ? `, NIP ${duplicate.tax_id}` : ''}.{' '}
            <button type="button" onClick={() => setDuplicate(null)}>
              {t('page.contractors.form.duplicate_dismiss')}
            </button>
          </div>
        )
      }
      foot={
        <>
          <span className="ge-drawer__foot-note">
            {t('page.contractors.form.only_name_required')}
          </span>
          <div className="ge-drawer__foot-end">
            <Button variant="text" onClick={onClose}>
              {t('cancel')}
            </Button>
            {!contractor && (
              <Button
                variant="outlined"
                loading={saving}
                onClick={() =>
                  void form.handleSubmit((data) => submit(data, true))()
                }
              >
                {t('page.contractors.form.save_and_next')}
              </Button>
            )}
            <Button
              variant="contained"
              loading={saving}
              onClick={() =>
                void form.handleSubmit((data) => submit(data, false))()
              }
            >
              {t('page.contractors.form.save')}
            </Button>
          </div>
        </>
      }
    >
      <Form form={form} onSubmit={(data) => submit(data, false)}>
        <div style={{ display: 'flex', minHeight: 0 }}>
          <DrawerColumn>
            <Fieldset
              tone="ident"
              label={t('page.contractors.form.section.identity')}
            >
              {isCompany ? (
                <>
                  <div className="ge-fs__row">
                    <Field
                      name="tax_id"
                      label={t('page.contractors.form.tax_id')}
                      emphasis="key"
                      style={{ flex: 1 }}
                    />
                    <Button
                      variant="outlined"
                      size="small"
                      loading={fetching}
                      onClick={() => void fetchFromRegistry()}
                    >
                      {t('page.contractors.form.registry_fetch')}
                    </Button>
                  </div>
                  {registryNote !== null && (
                    <FieldNote>{registryNote}</FieldNote>
                  )}
                </>
              ) : (
                <FieldRow columns="1fr 1fr">
                  <Field
                    name="first_name"
                    label={t('page.contractors.form.first_name')}
                  />
                  <Field
                    name="last_name"
                    label={t('page.contractors.form.last_name')}
                  />
                </FieldRow>
              )}

              <FieldRow columns="1fr" paddingTop={12}>
                <Field
                  name="name"
                  label={t('page.contractors.form.name')}
                  required
                />
              </FieldRow>

              <FieldRow columns={isCompany ? '1fr 1fr' : '1fr'} paddingTop={12}>
                <Field
                  name="short_name"
                  label={t('page.contractors.form.short_name')}
                />
                {isCompany && (
                  <Field
                    name="registry_id"
                    label={t('page.contractors.form.registry_id')}
                  />
                )}
              </FieldRow>
              <FieldNote>
                {t('page.contractors.form.short_name_hint')}
              </FieldNote>
            </Fieldset>

            <Fieldset
              tone="addr"
              label={t('page.contractors.form.section.address')}
            >
              <FieldRow columns="2fr .8fr .8fr">
                <Field
                  name="street"
                  label={t('page.contractors.form.street')}
                />
                <Field
                  name="building_number"
                  label={t('page.contractors.form.building')}
                />
                <Field
                  name="unit_number"
                  label={t('page.contractors.form.unit')}
                />
              </FieldRow>
              <FieldRow columns="1fr 2fr" paddingTop={12}>
                <Field
                  name="postal_code"
                  label={t('page.contractors.form.postal_code')}
                />
                <Field name="city" label={t('page.contractors.form.city')} />
              </FieldRow>
              <FieldNote>{t('page.contractors.form.address_hint')}</FieldNote>
            </Fieldset>
          </DrawerColumn>

          <DrawerColumn>
            <Fieldset
              tone="contact"
              label={t('page.contractors.form.section.contact')}
            >
              <FieldRow columns="1fr">
                <Field name="phone" label={t('page.contractors.form.phone')} />
              </FieldRow>
              <FieldRow columns="1fr" paddingTop={12}>
                <Field name="email" label={t('page.contractors.form.email')} />
              </FieldRow>
              <FieldRow columns="1fr" paddingTop={12}>
                <Field
                  name="website"
                  label={t('page.contractors.form.website')}
                />
              </FieldRow>
              <FieldNote>{t('page.contractors.form.contact_hint')}</FieldNote>
            </Fieldset>

            <Fieldset
              tone="terms"
              label={t('page.contractors.form.section.terms')}
            >
              <FieldRow columns="1fr 1.2fr">
                <Field
                  name="payment_days"
                  label={t('page.contractors.form.payment_days')}
                  emphasis="num"
                />
                <Field
                  name="credit_limit"
                  label={t('page.contractors.form.credit_limit')}
                  emphasis="num"
                />
              </FieldRow>
              <FieldNote>
                {t('page.contractors.form.credit_limit_hint')}
              </FieldNote>
              <div style={{ display: 'flex', gap: 20, paddingTop: 12 }}>
                <Toggle
                  name="is_active"
                  label={t('page.contractors.form.is_active')}
                />
                <Toggle
                  name="is_supplier"
                  label={t('page.contractors.form.is_supplier')}
                />
              </div>
            </Fieldset>

            <Field
              name="note"
              label={t('page.contractors.form.note')}
              placeholder={t('page.contractors.form.note_placeholder')}
            />
          </DrawerColumn>
        </div>
      </Form>
    </Drawer>
  );
}
