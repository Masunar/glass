import { useState } from 'react';

import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError } from '@salvon/utils/notify';

import type { AlertMark } from '@app/api/AlertsApi';
import { AlertOccurrencesApi } from '@app/api/AlertsApi';
import { Permission, SubPermission } from '@app/config/permission';
import { useHasPermission } from '@app/hook/use-permissions';

/**
 * Znaczniki alertów w wierszu.
 *
 * Etykieta mówi **co**, wartość obok **ile** („po terminie 12"). Sam
 * kolor nie niesie nic: znak bez legendy trzeba dopiero odgadnąć, więc
 * każdy znacznik ma słowo, a kolor go tylko wzmacnia.
 *
 * Kliknięcie odhacza alert — „wiem o tym". Odhaczony zostaje w wierszu,
 * tylko przygaszony: znika z czerwonych liczników, ale nie z oczu.
 * Ponowne kliknięcie cofa, bo odhaczenie przez pomyłkę jest gorsze niż
 * jego brak — sprawa milknie, a nikt o tym nie wie.
 *
 * Powyżej `max` znaczniki zwijają się w „+N". Wiersz listy ma jedną
 * linię wysokości i cztery chipy pod nazwą kontrahenta rozbijają
 * kolumnę liczb, którą oko czyta z góry na dół.
 */
export default function AlertChips({
  marks,
  max = 3,
  onChanged,
}: {
  marks: AlertMark[];
  max?: number;
  onChanged?: () => void;
}) {
  const t = useTranslation();
  const permitted = useHasPermission();
  const [busy, setBusy] = useState<number | null>(null);

  // Odhaczenie to decyzja o zleceniu, nie o konfiguracji alertow —
  // stad `orders.update`, a nie uprawnienie `alerts`.
  const mayAck = permitted(Permission.ORDERS, SubPermission.UPDATE);

  if (marks.length === 0) {
    return null;
  }

  const shown = marks.slice(0, max);
  const rest = marks.length - shown.length;

  const toggle = async (mark: AlertMark) => {
    if (mark.occurrence_id === null) {
      return;
    }

    setBusy(mark.occurrence_id);

    const { content, response } = mark.acknowledged
      ? await AlertOccurrencesApi.revoke(mark.occurrence_id)
      : await AlertOccurrencesApi.acknowledge(mark.occurrence_id);

    setBusy(null);

    if (!response.success) {
      notifyError(content?.data?.alert?.[0] ?? t('api.ise'));

      return;
    }

    onChanged?.();
  };

  const title = (mark: AlertMark) => {
    const parts: string[] = [];

    if (mark.since) {
      parts.push(t('page.alerts.mark_since', { date: mark.since }));
    }

    if (mark.acknowledged) {
      parts.push(
        t('page.alerts.mark_acknowledged', {
          who: mark.acknowledged_by ?? '—',
          date: mark.acknowledged_at ?? '',
        }),
      );
    }

    parts.push(
      t(mark.acknowledged ? 'page.alerts.mark_revoke' : 'page.alerts.mark_ack'),
    );

    return parts.join(' · ');
  };

  return (
    <div className="ge-marks">
      {shown.map((mark) => {
        const className = [
          'ge-mark',
          mark.acknowledged ? 'is-acknowledged' : '',
          busy === mark.occurrence_id ? 'is-busy' : '',
        ]
          .filter(Boolean)
          .join(' ');

        const style = mark.color
          ? ({ '--ge-mark': mark.color } as React.CSSProperties)
          : undefined;

        const body = (
          <>
            {mark.label}
            {mark.value !== null && mark.value !== '' && (
              <span className="ge-mark__value">{mark.value}</span>
            )}
          </>
        );

        if (!mayAck || mark.occurrence_id === null) {
          return (
            <span
              key={mark.code}
              className={className}
              style={style}
              title={title(mark)}
            >
              {body}
            </span>
          );
        }

        return (
          <button
            key={mark.code}
            type="button"
            className={className}
            style={style}
            title={title(mark)}
            disabled={busy === mark.occurrence_id}
            onClick={(event) => {
              // Znacznik siedzi w klikalnym wierszu — bez tego klik
              // otwiera zlecenie zamiast odhaczyc alert.
              event.stopPropagation();
              event.preventDefault();
              void toggle(mark);
            }}
          >
            {body}
          </button>
        );
      })}

      {rest > 0 && <span className="ge-mark ge-mark--rest">+{rest}</span>}
    </div>
  );
}
