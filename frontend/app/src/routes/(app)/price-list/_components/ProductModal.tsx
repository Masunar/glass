import { Box } from '@mui/material';

import { type Dispatch, type SetStateAction, useEffect } from 'react';

import { Flex } from '@salvon/components/div';
import { FormControl } from '@salvon/components/form';
import { FormModal } from '@salvon/components/modal';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import { CatalogApi } from '@app/api/CatalogApi';
import type { PriceRow } from '@app/api/PriceListApi';

type Props = {
  section: string;
  groupId: number | null;
  row: PriceRow | null;
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
  onSaved: () => void;
};

const units = [
  { value: 'm2', label: 'm²' },
  { value: 'mb', label: 'mb' },
  { value: 'pcs', label: 'szt.' },
];

const vatRates = [23, 8, 5, 0].map((rate) => ({
  value: rate,
  label: `${rate}%`,
}));

export default function ProductModal({
  section,
  groupId,
  row,
  open,
  setOpen,
  onSaved,
}: Props) {
  const t = useTranslation();
  const form = useForm();

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

  const handleSubmit = async (data: any) => {
    const { content, response } = await CatalogApi.saveProduct(
      { ...data, product_group_id: groupId },
      row?.product_id,
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
    onSaved();
  };

  return (
    <FormModal
      form={form}
      open={open}
      setOpen={setOpen}
      maxWidth="sm"
      title={t(
        row ? 'page.price_list.product.edit' : 'page.price_list.product.add',
      )}
      onSubmit={handleSubmit}
    >
      <Flex column gap={2}>
        <FormControl
          variant="text"
          name="name"
          label={t('page.price_list.product.name')}
          required
        />

        {isGlass && (
          <Flex gap={2}>
            <FormControl
              variant="number"
              name="thickness_mm"
              label={t('page.price_list.product.thickness')}
              required
              sx={{ flex: 1 }}
            />
            <FormControl
              variant="text"
              name="variant"
              label={t('page.price_list.product.variant')}
              helperText={t('page.price_list.product.variant_hint')}
              sx={{ flex: 2 }}
            />
          </Flex>
        )}

        {isFittings && (
          <Flex gap={2}>
            <FormControl
              variant="text"
              name="finish"
              label={t('page.price_list.product.finish')}
              sx={{ flex: 1 }}
            />
            <FormControl
              variant="text"
              name="dimension"
              label={t('page.price_list.product.dimension')}
              sx={{ flex: 1 }}
            />
          </Flex>
        )}

        {isServices && (
          <FormControl
            variant="number"
            name="glass_thickness_mm"
            label={t('page.price_list.product.service_thickness')}
            helperText={t('page.price_list.product.service_thickness_hint')}
          />
        )}

        <Flex gap={2}>
          <FormControl
            variant="text"
            name="code"
            label={t('page.price_list.product.code')}
            sx={{ flex: 1 }}
          />
          <FormControl
            variant="text"
            name="manufacturer_code"
            label={t('page.price_list.product.manufacturer_code')}
            sx={{ flex: 1 }}
          />
        </Flex>

        <Flex gap={2}>
          <Box sx={{ flex: 1 }}>
            <FormControl
              variant="select"
              name="unit"
              label={t('page.price_list.product.unit')}
              options={units}
            />
          </Box>
          <Box sx={{ flex: 1 }}>
            <FormControl
              variant="select"
              name="vat_rate"
              label={t('page.price_list.product.vat')}
              options={vatRates}
            />
          </Box>
          <FormControl
            variant="number"
            name="purchase_net_price"
            label={t('page.price_list.product.purchase')}
            helperText={t('page.price_list.product.purchase_hint')}
            sx={{ flex: 2 }}
          />
        </Flex>

        {isGlass && (
          <FormControl
            variant="switch"
            name="is_tempered_by_default"
            label={t('page.price_list.product.tempered')}
          />
        )}

        <FormControl
          variant="switch"
          name="is_made_to_order"
          label={t('page.price_list.product.made_to_order')}
          helperText={t('page.price_list.product.made_to_order_hint')}
        />

        <FormControl
          variant="switch"
          name="is_active"
          label={t('page.price_list.active')}
          helperText={t('page.price_list.active_hint')}
        />
      </Flex>
    </FormModal>
  );
}
