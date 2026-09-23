import type { AlertMark } from '@app/api/AlertsApi';

/**
 * Znaczniki alertów w wierszu.
 *
 * Etykieta mówi **co**, wartość obok **ile** („po terminie 12"). Sam
 * kolor nie niesie nic: znak bez legendy trzeba dopiero odgadnąć, więc
 * każdy znacznik ma słowo, a kolor go tylko wzmacnia.
 *
 * Powyżej `max` znaczniki zwijają się w „+N". Wiersz listy ma jedną
 * linię wysokości i cztery chipy pod nazwą kontrahenta rozbijają
 * kolumnę liczb, którą oko czyta z góry na dół.
 */
export default function AlertChips({
  marks,
  max = 3,
}: {
  marks: AlertMark[];
  max?: number;
}) {
  if (marks.length === 0) {
    return null;
  }

  const shown = marks.slice(0, max);
  const rest = marks.length - shown.length;

  return (
    <div className="ge-marks">
      {shown.map((mark) => (
        <span
          key={mark.code}
          className="ge-mark"
          style={
            mark.color
              ? ({ '--ge-mark': mark.color } as React.CSSProperties)
              : undefined
          }
          title={mark.since ? `od ${mark.since}` : undefined}
        >
          {mark.label}
          {mark.value !== null && mark.value !== '' && (
            <span className="ge-mark__value">{mark.value}</span>
          )}
        </span>
      ))}

      {rest > 0 && <span className="ge-mark ge-mark--rest">+{rest}</span>}
    </div>
  );
}
