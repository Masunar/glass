import dayjs from 'dayjs';
import type { TFunction } from 'i18next';

import {
  emailRegex,
  numberRegex,
  passwordRegex,
  polishPhoneNumber,
  positiveIntRegex,
} from '@salvon/consts/regex';

export const requiredRule = (t: TFunction) => {
  return {
    required: t('validation.required'),
  };
};

export const emailRule = (t: TFunction) => {
  return {
    pattern: {
      value: emailRegex,
      message: t('validation.email.invalid'),
    },
  };
};

export const requiredEmailRule = (t: TFunction) => {
  return {
    ...requiredRule(t),
    ...emailRule(t),
  };
};

export const minLengthRule = (t: TFunction, length: number) => {
  return {
    minLength: {
      value: length,
      message: t('validation.length.min', { min: length }),
    },
  };
};

export const maxLengthRule = (t: TFunction, length: number) => {
  return {
    minLength: {
      value: length,
      message: t('validation.length.max', { min: length }),
    },
  };
};

export const numberRule = (t: TFunction) => {
  return {
    pattern: {
      value: numberRegex,
      message: t('validation.number.invalid'),
    },
  };
};

export const requiredNumberRule = (t: TFunction) => {
  return {
    ...requiredRule(t),
    ...numberRule(t),
  };
};

export const positiveIntRule = (t: TFunction) => {
  return {
    pattern: {
      value: positiveIntRegex,
      message: t('validation.number.positive_int'),
    },
  };
};

export const requiredPositiveIntRule = (t: TFunction) => {
  return {
    ...requiredRule(t),
    ...positiveIntRule(t),
  };
};

export const dateRule = (t: TFunction) => {
  return {
    validate: (value: any) => {
      if (!isDateValid(value)) {
        return t('validation.date.invalid');
      }

      if (!dateTooEarly(value)) {
        return t('validation.date.too_early');
      }

      return true;
    },
  };
};

export const requiredDateRule = (t: TFunction) => {
  return {
    ...requiredRule(t),
    ...dateRule(t),
  };
};

const isDateValid = (value: string) => {
  if (!value || value.length === 0) {
    return true;
  }

  return dayjs(value).isValid();
};

const dateTooEarly = (value: string) => {
  if (!value || value.length === 0) {
    return true;
  }

  const date = dayjs(value);
  const minDate = dayjs('1900-01-01');

  return date.isValid() && date.isAfter(minDate);
};

export const nipRule = (t: TFunction) => {
  return {
    ...requiredRule(t),
    validate: (value: any) => {
      return isNipValid(value) || t('validation.nip.invalid');
    },
  };
};

export const isNipValid = (value: any) => {
  const weight = [6, 5, 7, 2, 3, 4, 5, 6, 7];
  const controlNumber = parseInt(value.substring(9, 10));
  const weightCount = weight.length;

  let sum = 0;
  for (let i = 0; i < weightCount; i++) {
    sum += parseInt(value.slice(i, i + 1)) * weight[i];
  }

  return sum % 11 === controlNumber;
};

export const polishPhoneNumberRule = (t: TFunction) => {
  return {
    ...requiredRule(t),
    pattern: {
      value: polishPhoneNumber,
      message: t('validation.positive_int'),
    },
  };
};

export const passwordRule = (t: TFunction) => {
  return {
    ...requiredRule(t),
    pattern: {
      value: passwordRegex,
      message: t('validation.password.criteria'),
    },
  };
};

export const confirmPasswordRule = (t: TFunction, password: string) => {
  return {
    ...passwordRule(t),
    validate: (value: string) => {
      if (password !== value) {
        return t('validation.password.mismatch');
      }

      return true;
    },
  };
};
