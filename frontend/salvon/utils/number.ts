import { intVal } from '@salvon/utils/type-transform';

export const randomNumberFromRange = (
  min: number = 0,
  max: number = 100000,
  asInt: boolean = true,
): number => {
  min = Math.ceil(min);
  max = Math.floor(max);

  // The maximum is inclusive and the minimum is inclusive
  const random = Math.random() * (max - min + 1) + min;

  if (!asInt) {
    return random;
  }

  return Math.floor(random);
};

export const numberFormat = (
  value: number,
  decimals = 2,
  locale: Intl.LocalesArgument = 'pl-PL',
): string => {
  if (
    isNaN(value) ||
    value === 0 ||
    value === Infinity ||
    value === -Infinity
  ) {
    return '0';
  }

  const multiplier = Math.pow(10, decimals);
  const numberValue = Math.round(Number(value) * multiplier) / multiplier;

  return numberValue.toLocaleString(locale, {
    useGrouping: true,
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });
};

export const numberFormatPrefix = (
  value: number,
  prefix: string = 'PLN',
  decimals = 2,
  locale: Intl.LocalesArgument = 'pl-PL',
): string => {
  return `${prefix} ${numberFormat(value, decimals, locale)}`;
};

export const numberFormatSuffix = (
  value: number,
  suffix: string = 'PLN',
  decimals = 2,
  locale: Intl.LocalesArgument = 'pl-PL',
): string => {
  return `${numberFormat(value, decimals, locale)} ${suffix}`;
};

export const sanitizeStringNumber = (
  v: string,
  precision: number | undefined = undefined,
) => {
  let value = v;
  const basicSanitizedValue = value.replace(/,/g, '.').replace(/[^0-9.-]/g, '');

  if (['', '-'].includes(basicSanitizedValue)) {
    return basicSanitizedValue;
  }

  if (precision !== undefined) {
    precision = Math.max(0, Math.floor(precision));
  }

  const isInteger = precision === 0;
  const isNegative = basicSanitizedValue.includes('-');
  const startsWithZero = basicSanitizedValue.startsWith('0');

  value = value.replace(/,/g, '.').replace(/[^0-9.]/g, '');

  if (isInteger) {
    value = value.replaceAll('.', '');
  } else {
    value = value.replace(/\./g, (_, i, s) =>
      s.indexOf('.') === i ? '.' : '',
    );
  }

  if (/^\d+(\.\d*)?$/.test(value)) {
    value = value.replace(/^0+/, '') || '0';
  }

  if (!isInteger && value.startsWith('.')) {
    value = `0${value}`;
  }

  if (!isInteger && startsWithZero && !value.startsWith('0')) {
    value = `0.${value}`;
  }

  if (precision) {
    value = value.replace(
      new RegExp(`^(-?\\d+\\.\\d{0,${precision}}).*`),
      '$1',
    );
  }

  if (isNegative) {
    value = `-${value}`;
  }

  return value;
};
