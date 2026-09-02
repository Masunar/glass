export const boolVal = (value: any): boolean => {
  return Boolean(value);
};

export const floatVal = (
  value: any,
  precision: number = 2,
  replaceComma: boolean = false,
): number => {
  if (!value) {
    value = 0;
  }

  if (replaceComma) {
    value = `${value}`.replace(',', '.');
  }

  return +(+value).toFixed(precision);
};

export const intVal = (value: any): number => {
  if (!value) {
    value = 0;
  }

  if (value.replace) {
    value = value.replace(',', '');
    value = value.replace('.', '');
  }

  value = +value;

  if (isNaN(value)) {
    value = 0;
  }

  return parseInt(value ?? 0);
};

export const strVal = (value: any): string => {
  if (!value) {
    return '';
  }

  return String(value);
};
