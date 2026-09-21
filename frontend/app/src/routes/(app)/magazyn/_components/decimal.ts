/**
 * Ilość bez sztucznych zer. Magazyn liczy sztuki, ale model dopuszcza
 * ułamek, więc „12" ma zostać dwunastką, a nie „12.000".
 */
export const decimal = (value: number): string =>
  Number.isInteger(value)
    ? String(value)
    : value.toFixed(3).replace(/0+$/, '').replace(/\.$/, '');
