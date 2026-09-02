export function formatDate(value: string | null | undefined): string {
  if (!value) return '-';
  const d = new Date(value);
  if (isNaN(d.getTime())) return value;
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}

export function formatNumber(value: number, decimals = 2) {
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

  return numberValue.toLocaleString('pl-PL', {
    useGrouping: true,
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });
}

export function formatPrice(
  value: number,
  currency: any = 'PLN',
  decimals = 2,
) {
  return `${formatNumber(value, decimals)}${!!currency ? ` ${currency.symbol}` : ''}`;
}
