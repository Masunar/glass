import { type Dispatch, type SetStateAction, useEffect } from 'react';

import { Flex } from '@salvon/components/div';
import { FormControl } from '@salvon/components/form';
import { FormModal } from '@salvon/components/modal';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import {
  DictionariesApi,
  type Dictionary,
  type DictionaryField,
  type DictionaryRow,
} from '@app/api/DictionariesApi';

type Props = {
  dictionary: Dictionary;
  row: DictionaryRow | null;
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
  onSaved: () => void;
};

/** Wartość startowa pola — pusty string zamiast null, bo tego chce input. */
function initialValue(field: DictionaryField, row: DictionaryRow | null) {
  if (row === null) {
    return field.type === 'boolean' ? field.key === 'is_active' : '';
  }

  const value = row[field.key];

  if (field.type === 'boolean') {
    return value === true;
  }

  return value === null || value === undefined ? '' : String(value);
}

/**
 * Formularz wiersza słownika budowany z opisu pól.
 *
 * Jeden modal na wszystkie zakładki: układ niesie definicja z backendu,
 * więc dodanie pola nie wymaga tknięcia tego pliku.
 */
export default function RowModal({
  dictionary,
  row,
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

    form.reset(
      Object.fromEntries(
        dictionary.fields.map((field) => [field.key, initialValue(field, row)]),
      ),
    );
  }, [open, dictionary.slug, row?.id]);

  const handleSubmit = async (data: Record<string, unknown>) => {
    const values = Object.fromEntries(
      dictionary.fields.map((field) => {
        const value = data[field.key];

        if (field.type === 'boolean') {
          return [field.key, value === true];
        }

        return [field.key, value === '' ? null : value];
      }),
    );

    const { content, response } = await DictionariesApi.save(
      dictionary.slug,
      values,
      row?.id,
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
      title={`${dictionary.label} — ${t(row ? 'edit' : 'add')}`}
      onSubmit={handleSubmit}
    >
      <Flex column gap={2}>
        {dictionary.fields.map((field) => (
          <Control key={field.key} field={field} />
        ))}
      </Flex>
    </FormModal>
  );
}

function Control({ field }: { field: DictionaryField }) {
  const common = {
    name: field.key,
    label: field.label,
    required: field.required,
    helperText: field.hint ?? undefined,
  };

  if (field.type === 'boolean') {
    return <FormControl variant="switch" {...common} />;
  }

  if (field.type === 'select' || field.type === 'reference') {
    return <FormControl variant="select" {...common} options={field.options} />;
  }

  if (field.type === 'integer') {
    return <FormControl variant="integer" {...common} />;
  }

  if (field.type === 'decimal') {
    return <FormControl variant="number" {...common} />;
  }

  return <FormControl variant="text" {...common} />;
}
