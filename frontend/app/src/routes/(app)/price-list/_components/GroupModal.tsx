import { type Dispatch, type SetStateAction, useEffect } from 'react';

import { Flex } from '@salvon/components/div';
import { FormControl } from '@salvon/components/form';
import { FormModal } from '@salvon/components/modal';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import { CatalogApi } from '@app/api/CatalogApi';
import type { PriceGroup } from '@app/api/PriceListApi';

type Props = {
  section: string;
  group: PriceGroup | null;
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
  onSaved: (groupId: number) => void;
};

export default function GroupModal({
  section,
  group,
  open,
  setOpen,
  onSaved,
}: Props) {
  const t = useTranslation();
  const form = useForm();

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

  const handleSubmit = async (data: any) => {
    const { content, response } = await CatalogApi.saveGroup(
      { ...data, section },
      group?.id,
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
    onSaved(Number(content?.data?.id));
  };

  return (
    <FormModal
      form={form}
      open={open}
      setOpen={setOpen}
      maxWidth="sm"
      title={t(
        group ? 'page.price_list.group.edit' : 'page.price_list.group.add',
      )}
      onSubmit={handleSubmit}
    >
      <Flex column gap={2}>
        <FormControl
          variant="text"
          name="name"
          label={t('page.price_list.group.name')}
          required
        />
        <FormControl
          variant="text"
          name="manufacturer"
          label={t('page.price_list.group.manufacturer')}
          helperText={t('page.price_list.group.manufacturer_hint')}
        />
        <FormControl
          variant="text"
          name="series"
          label={t('page.price_list.group.series')}
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
