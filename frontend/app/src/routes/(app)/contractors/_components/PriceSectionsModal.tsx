import { Typography } from '@mui/material';

import { type Dispatch, type SetStateAction, useEffect, useState } from 'react';

import { Flex } from '@salvon/components/div';
import { FormControl } from '@salvon/components/form';
import { FormModal } from '@salvon/components/modal';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifySuccess } from '@salvon/utils/notify';

import type { ContractorCard } from '@app/api/ContractorsApi';
import { ContractorsApi } from '@app/api/ContractorsApi';
import { PriceListApi } from '@app/api/PriceListApi';
import { priceListSections } from '@app/routes/(app)/price-list/_components/sections';

type Props = {
  card: ContractorCard | null;
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
  onSaved: () => void;
};

type Options = Record<string, { value: number; label: string }[]>;

/**
 * Poziom 2 ustalania ceny: sekcja cenowa kontrahenta w każdej z pięciu
 * sekcji asortymentu.
 *
 * Puste pole nie jest błędem — oznacza, że obowiązuje sekcja domyślna,
 * i tak jest napisane pod polem. Blokada wyceny przy braku przypisania
 * oznaczałaby, że nowego klienta nie da się wycenić, dopóki ktoś nie
 * uzupełni pięciu wierszy.
 */
export default function PriceSectionsModal({
  card,
  open,
  setOpen,
  onSaved,
}: Props) {
  const t = useTranslation();
  const form = useForm();
  const [options, setOptions] = useState<Options>({});

  useEffect(() => {
    if (!open || !card) {
      return;
    }

    form.reset(
      Object.fromEntries(
        card.price_sections.map((row) => [
          row.section,
          row.price_section_id ?? '',
        ]),
      ),
    );

    void (async () => {
      const loaded: Options = {};

      for (const section of priceListSections) {
        const { content } = await PriceListApi.matrix(section.value);
        loaded[section.value] = (content?.data?.columns ?? []).map(
          (column: { id: number; name: string }) => ({
            value: column.id,
            label: column.name,
          }),
        );
      }

      setOptions(loaded);
    })();
  }, [open, card?.contractor.id]);

  const handleSubmit = async (data: any) => {
    if (!card) {
      return;
    }

    const sections = Object.fromEntries(
      Object.entries(data).map(([key, value]) => [
        key,
        value === '' || value === undefined ? null : Number(value),
      ]),
    );

    const { content } = await ContractorsApi.savePriceSections(
      card.contractor.id,
      sections,
    );

    if (!validationCompleted(content, form.setError, t)) {
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
      title={t('page.contractors.price_sections.title')}
      onSubmit={handleSubmit}
    >
      <Flex column gap={2}>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          {t('page.contractors.price_sections.lead')}
        </Typography>

        {(card?.price_sections ?? []).map((row) => (
          <FormControl
            key={row.section}
            variant="select"
            name={row.section}
            label={t(`page.price_list.section.${row.section}`)}
            options={[
              {
                value: '',
                label: t('page.contractors.price_sections.default'),
              },
              ...(options[row.section] ?? []),
            ]}
            helperText={
              row.price_section_id === null && row.default_name
                ? t('page.contractors.price_sections.fallback', {
                    name: row.default_name,
                  })
                : undefined
            }
          />
        ))}
      </Flex>
    </FormModal>
  );
}
