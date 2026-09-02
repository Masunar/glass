import type { TFunction } from 'i18next';

import { requiredRule } from '@salvon/utils/validation-rules';

const phoneRegex = /^(?=(?:\D*\d){7,})\+?[\d\s()-]{7,20}$/;
const postalCodeRegex = /^\d{2}-\d{3}$/;
const buildingNumberRegex = /^\d+[A-Za-z]?(\/\d+[A-Za-z]?)?$/;
const apartmentNumberRegex = /^\d+[A-Za-z]?$/;

export const isValidPhone = (value: string): boolean =>
  phoneRegex.test(value.trim());

export const isValidPostalCode = (value: string): boolean =>
  postalCodeRegex.test(value.trim());

export const postalCodeRule = (t: TFunction) => ({
  pattern: {
    value: postalCodeRegex,
    message: t('validation.postal_code.invalid'),
  },
});

export const requiredPostalCodeRule = (t: TFunction) => ({
  ...requiredRule(t),
  ...postalCodeRule(t),
});

export const buildingNumberRule = (t: TFunction) => ({
  pattern: {
    value: buildingNumberRegex,
    message: t('validation.building_number.invalid'),
  },
});

export const requiredBuildingNumberRule = (t: TFunction) => ({
  ...requiredRule(t),
  ...buildingNumberRule(t),
});

export const apartmentNumberRule = (t: TFunction) => ({
  pattern: {
    value: apartmentNumberRegex,
    message: t('validation.apartment_number.invalid'),
  },
});

export const phoneRule = (t: TFunction) => ({
  pattern: {
    value: phoneRegex,
    message: t('validation.phone.invalid'),
  },
});

export const requiredPhoneRule = (t: TFunction) => ({
  ...requiredRule(t),
  ...phoneRule(t),
});
