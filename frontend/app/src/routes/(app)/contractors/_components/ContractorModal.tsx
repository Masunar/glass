import { Box } from '@mui/material';

import { type Dispatch, type SetStateAction, useEffect } from 'react';

import { Flex } from '@salvon/components/div';
import { FormControl } from '@salvon/components/form';
import { FormModal } from '@salvon/components/modal';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import { type ContractorCard, ContractorsApi } from '@app/api/ContractorsApi';

type Props = {
  card: ContractorCard | null;
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
  onSaved: (id: number) => void;
};

const types = [
  { value: 'company', label: 'Firma' },
  { value: 'person', label: 'Osoba prywatna' },
];

export default function ContractorModal({
  card,
  open,
  setOpen,
  onSaved,
}: Props) {
  const t = useTranslation();
  const form = useForm();

  const contractor = card?.contractor;
  const address = card?.addresses?.registered ?? null;
  const type = form.watch('type') ?? contractor?.type ?? 'company';

  useEffect(() => {
    if (!open) {
      return;
    }

    form.reset({
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

  const handleSubmit = async (data: any) => {
    const { street, building_number, unit_number, postal_code, city, ...rest } =
      data;

    const { content, response } = await ContractorsApi.save(
      {
        ...rest,
        address: { street, building_number, unit_number, postal_code, city },
      },
      contractor?.id,
    );

    if (!validationCompleted(content, form.setError, t)) {
      return;
    }

    if (!response.success) {
      notifyError(t('api.ise'));
      return;
    }

    notifySuccess(t('api.save_success'));
    setOpen(false);
    onSaved(Number(content?.data?.id ?? contractor?.id));
  };

  return (
    <FormModal
      form={form}
      open={open}
      setOpen={setOpen}
      maxWidth="md"
      title={t(
        contractor ? 'page.contractors.form.edit' : 'page.contractors.form.add',
      )}
      onSubmit={handleSubmit}
    >
      <Flex column gap={2}>
        <Flex gap={2}>
          <Box sx={{ flex: 1 }}>
            <FormControl
              variant="select"
              name="type"
              label={t('page.contractors.form.type')}
              options={types}
            />
          </Box>
          <FormControl
            variant="text"
            name="name"
            label={t('page.contractors.form.name')}
            required
            sx={{ flex: 3 }}
          />
        </Flex>

        <Flex gap={2}>
          <FormControl
            variant="text"
            name="short_name"
            label={t('page.contractors.form.short_name')}
            helperText={t('page.contractors.form.short_name_hint')}
            sx={{ flex: 2 }}
          />
          <FormControl
            variant="text"
            name="tax_id"
            label={t('page.contractors.form.tax_id')}
            helperText={
              type === 'company'
                ? t('page.contractors.form.tax_id_hint')
                : t('page.contractors.form.tax_id_person')
            }
            sx={{ flex: 1 }}
          />
          <FormControl
            variant="text"
            name="registry_id"
            label={t('page.contractors.form.registry_id')}
            sx={{ flex: 1 }}
          />
        </Flex>

        {type === 'person' && (
          <Flex gap={2}>
            <FormControl
              variant="text"
              name="first_name"
              label={t('page.contractors.form.first_name')}
              sx={{ flex: 1 }}
            />
            <FormControl
              variant="text"
              name="last_name"
              label={t('page.contractors.form.last_name')}
              sx={{ flex: 1 }}
            />
          </Flex>
        )}

        <Flex gap={2}>
          <FormControl
            variant="text"
            name="street"
            label={t('page.contractors.form.street')}
            sx={{ flex: 3 }}
          />
          <FormControl
            variant="text"
            name="building_number"
            label={t('page.contractors.form.building')}
            sx={{ flex: 1 }}
          />
          <FormControl
            variant="text"
            name="unit_number"
            label={t('page.contractors.form.unit')}
            sx={{ flex: 1 }}
          />
        </Flex>

        <Flex gap={2}>
          <FormControl
            variant="text"
            name="postal_code"
            label={t('page.contractors.form.postal_code')}
            sx={{ flex: 1 }}
          />
          <FormControl
            variant="text"
            name="city"
            label={t('page.contractors.form.city')}
            sx={{ flex: 3 }}
          />
        </Flex>

        <Flex gap={2}>
          <FormControl
            variant="text"
            name="phone"
            label={t('page.contractors.form.phone')}
            helperText={t('page.contractors.form.contact_hint')}
            sx={{ flex: 1 }}
          />
          <FormControl
            variant="text"
            name="email"
            label={t('page.contractors.form.email')}
            sx={{ flex: 1 }}
          />
          <FormControl
            variant="text"
            name="website"
            label={t('page.contractors.form.website')}
            sx={{ flex: 1 }}
          />
        </Flex>

        <Flex gap={2}>
          <FormControl
            variant="number"
            name="payment_days"
            label={t('page.contractors.form.payment_days')}
            sx={{ flex: 1 }}
          />
          <FormControl
            variant="number"
            name="credit_limit"
            label={t('page.contractors.form.credit_limit')}
            helperText={t('page.contractors.form.credit_limit_hint')}
            sx={{ flex: 1 }}
          />
        </Flex>

        <FormControl
          variant="text"
          name="note"
          label={t('page.contractors.form.note')}
          multiline
          minRows={2}
        />

        <Flex gap={3}>
          <FormControl
            variant="switch"
            name="is_supplier"
            label={t('page.contractors.form.is_supplier')}
          />
          <FormControl
            variant="switch"
            name="is_active"
            label={t('page.contractors.form.is_active')}
          />
        </Flex>
      </Flex>
    </FormModal>
  );
}
