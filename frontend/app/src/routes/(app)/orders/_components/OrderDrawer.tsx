import ContractorPicker from './ContractorPicker';
import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { Form } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import { type ContractorRow, ContractorsApi } from '@app/api/ContractorsApi';
import { type OrderFormOptions, OrdersApi } from '@app/api/OrdersApi';
import ContractorDrawer from '@app/components/contractor/ContractorDrawer';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Field, { Choice } from '@app/components/drawer/Field';
import Fieldset, { FieldNote } from '@app/components/drawer/Fieldset';

type Props = {
  open: boolean;
  onClose: () => void;
  onCreated: (id: number, number: number) => void;
};

type Method = 'pickup' | 'installation' | 'delivery';

const empty = {
  location_id: '',
  pickup_location_id: '',
  invoice_type_id: '',
  client_deadline: '',
  delivery_address: '',
  delivery_contact: '',
  short_note: '',
};

/**
 * Zakładanie zlecenia.
 *
 * Wymagane są dwie rzeczy: kontrahent i sposób wydania (z miejscem, do
 * którego towar pojedzie). Reszta — termin, typ faktury, uwaga — da się
 * uzupełnić później. Formularz, który żąda kompletu na starcie, uczy
 * wpisywania daty z sufitu, żeby przejść dalej.
 */
export default function OrderDrawer({ open, onClose, onCreated }: Props) {
  const t = useTranslation();
  const form = useForm();
  const [options, setOptions] = useState<OrderFormOptions | null>(null);
  const [contractor, setContractor] = useState<ContractorRow | null>(null);
  const [contractorError, setContractorError] = useState<string | null>(null);
  const [method, setMethod] = useState<Method>('pickup');
  const [saving, setSaving] = useState(false);
  const [newContractor, setNewContractor] = useState<string | null>(null);

  useEffect(() => {
    if (!open) {
      return;
    }

    setContractor(null);
    setContractorError(null);
    setNewContractor(null);

    void (async () => {
      const { content } = await OrdersApi.formOptions();
      const data: OrderFormOptions | undefined = content?.data;

      if (!data) {
        return;
      }

      setOptions(data);
      setMethod((data.defaults.delivery_method as Method) ?? 'pickup');

      form.reset({
        ...empty,
        location_id: data.defaults.branch_id ?? '',
        invoice_type_id: data.defaults.invoice_type_id ?? '',
        pickup_location_id: data.pickup_points[0]?.id ?? '',
      });
    })();
  }, [open]);

  const isPickup = method === 'pickup';

  /**
   * Kartoteka założona z panelu wraca tu od razu wybrana. Bez tego
   * człowiek zakłada klienta, wraca do zlecenia i musi go wyszukać —
   * choć przed chwilą wpisał jego nazwę.
   */
  const pickCreated = async (id: number) => {
    setNewContractor(null);

    const { content } = await ContractorsApi.card(id);
    const row: ContractorRow | undefined = content?.data?.contractor;

    if (row) {
      setContractor(row);
      setContractorError(null);
    }
  };

  const submit = async (data: any) => {
    if (!contractor) {
      setContractorError(t('page.orders.form.contractor_required'));

      return;
    }

    setSaving(true);

    const { content, response } = await OrdersApi.create({
      ...data,
      contractor_id: contractor.id,
      delivery_method: method,
    });

    setSaving(false);

    if (!validationCompleted(content, form.setError, t)) {
      // Kontrahent nie jest polem formularza, wiec jego blad nie trafi
      // do formState — trzeba go pokazac osobno.
      setContractorError(content?.errors?.contractor_id?.[0] ?? null);

      return;
    }

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    notifySuccess(t('api.save_success'));
    onCreated(Number(content?.data?.id), Number(content?.data?.number));
  };

  return (
    <>
      <Drawer
        open={open}
        // Panel kartoteki lezy na wierzchu — Esc i klikniecie w tlo
        // naleza do niego, nie do formularza pod spodem.
        onClose={() => newContractor === null && onClose()}
        kicker={t('page.orders.title')}
        title={t('page.orders.form.add')}
        narrow
        foot={
          <>
            <span className="ge-drawer__foot-note">
              {t('page.orders.form.foot_note', {
                status: options?.status ?? '—',
              })}
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
                {t('page.orders.form.save')}
              </Button>
            </div>
          </>
        }
      >
        <Form form={form} onSubmit={submit}>
          <DrawerColumn>
            <Fieldset tone="ident" label={t('page.orders.form.section.client')}>
              <ContractorPicker
                value={contractor}
                error={contractorError}
                onPick={(row) => {
                  setContractor(row);
                  setContractorError(null);
                }}
                onCreate={(name) => setNewContractor(name)}
              />
              <FieldNote>{t('page.orders.form.contractor_note')}</FieldNote>
            </Fieldset>

            <Fieldset
              tone="addr"
              label={t('page.orders.form.section.handover')}
            >
              <div className="ge-switch ge-switch--wide">
                {(['pickup', 'installation', 'delivery'] as Method[]).map(
                  (option) => (
                    <button
                      key={option}
                      type="button"
                      className={method === option ? 'is-active' : ''}
                      onClick={() => setMethod(option)}
                    >
                      {t(`page.orders.handover.${option}`)}
                    </button>
                  ),
                )}
              </div>

              {isPickup ? (
                <Choice
                  name="pickup_location_id"
                  label={t('page.orders.form.pickup_point')}
                  required
                  options={(options?.pickup_points ?? []).map((point) => ({
                    value: point.id,
                    label: point.name,
                  }))}
                />
              ) : (
                <>
                  <Field
                    name="delivery_address"
                    label={t('page.orders.form.address')}
                    required
                  />
                  <Field
                    name="delivery_contact"
                    label={t('page.orders.form.contact')}
                  />
                </>
              )}
            </Fieldset>

            <Fieldset tone="terms" label={t('page.orders.form.section.terms')}>
              <Field
                name="client_deadline"
                label={t('page.orders.form.deadline')}
                type="date"
              />
              <Choice
                name="invoice_type_id"
                label={t('page.orders.form.invoice_type')}
                emptyLabel={t('page.orders.form.invoice_type_later')}
                options={(options?.invoice_types ?? []).map((type) => ({
                  value: type.id,
                  label: `${type.name} · VAT ${type.vat_rate} %`,
                }))}
              />
              <Choice
                name="location_id"
                label={t('page.orders.form.branch')}
                emptyLabel={t('page.orders.form.branch_none')}
                options={(options?.branches ?? []).map((branch) => ({
                  value: branch.id,
                  label: branch.name,
                }))}
              />
            </Fieldset>

            <Fieldset tone="contact" label={t('page.orders.form.section.note')}>
              <Field
                name="short_note"
                label={t('page.orders.form.short_note')}
                placeholder={t('page.orders.form.short_note_hint')}
              />
            </Fieldset>
          </DrawerColumn>
        </Form>
      </Drawer>

      <ContractorDrawer
        card={null}
        open={newContractor !== null}
        layer={1}
        initialName={newContractor ?? ''}
        onClose={() => setNewContractor(null)}
        onSaved={(id) => void pickCreated(id)}
      />
    </>
  );
}
