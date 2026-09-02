import type { TFunction } from 'i18next';

export const getAttributeTypeOptions = (t: TFunction) => [
  { label: t('attribute_type.select'), value: 'select' },
  { label: t('attribute_type.multiselect'), value: 'multiselect' },
  { label: t('attribute_type.boolean'), value: 'boolean' },
  { label: t('attribute_type.numeric'), value: 'numeric' },
  { label: t('attribute_type.text'), value: 'text' },
  { label: t('attribute_type.date'), value: 'date' },
];
