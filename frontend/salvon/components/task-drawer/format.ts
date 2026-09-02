import dayjs from 'dayjs';
import type { Dayjs } from 'dayjs';

export function formatDateTime(value?: string | number | Dayjs) {
  if (value === undefined || value === null) return '';
  return dayjs(value).format('DD.MM.YYYY HH:mm');
}

export function initialOf(name?: string) {
  return name?.trim()?.[0]?.toUpperCase() ?? '?';
}
