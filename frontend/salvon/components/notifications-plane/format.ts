import dayjs from 'dayjs';
import type { Dayjs } from 'dayjs';

export function formatNotificationDate(
  value?: string | number | Date | Dayjs,
  labels?: { today?: string; yesterday?: string },
) {
  if (!value) return '';
  const date = dayjs(value);
  if (!date.isValid()) return '';

  const time = date.format('HH:mm');
  const days = dayjs().startOf('day').diff(date.startOf('day'), 'day');

  if (days === 0) return `${labels?.today ?? 'Today'} ${time}`;
  if (days === 1) return `${labels?.yesterday ?? 'Yesterday'} ${time}`;
  return date.format('YYYY-MM-DD HH:mm');
}
