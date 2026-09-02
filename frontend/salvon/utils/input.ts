import { intVal } from '@salvon/utils/type-transform';

export const onInputNumberSanitize = (
  inputValue: string,
  precision: number = 0,
) => {
  const isNegative = inputValue.startsWith('-');

  if (inputValue === '') {
    return '';
  }

  if (inputValue === '-') {
    return '-';
  }

  let value = inputValue
    .replace(/,/g, '.')
    .replace(/[^0-9.]/g, '')
    .replace(/(\..*)\./g, '$1');

  if (value === '' || value === '.') {
    value = '0';
  } else if (value.startsWith('0') && !value.startsWith('0.')) {
    value = value.replace(/^0+/, '');
    if (value === '' || value.startsWith('.')) {
      value = '0' + value;
    }
  }

  if (precision >= 0) {
    const parts = value.split('.');
    if (parts[1]) {
      parts[1] = parts[1].slice(0, precision);
      value = parts.join('.');
    }
  } else {
    value = value.replace('.', '');
  }

  if (intVal(value) > 0 && isNegative) {
    return -value;
  }

  return value;
};
