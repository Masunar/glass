import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError } from '@salvon/utils/notify';

import type { OrderDiscountRow } from '@app/api/OrdersApi';
import { OrdersApi } from '@app/api/OrdersApi';

type Props = {
  orderId: number;
  rows: OrderDiscountRow[];
  onSaved: () => void;
};

/**
 * Rabat na zleceniu — czwarty poziom ceny, nadawany per sekcja.
 *
 * Limit roli jest pokazany przy polu, a nie dopiero po odrzuceniu
 * zapisu: handlowiec ma wiedzieć, ile wolno, zanim zacznie negocjować
 * z klientem. Serwer i tak sprawdza go drugi raz.
 */
export default function DiscountPanel({ orderId, rows, onSaved }: Props) {
  const t = useTranslation();
  const [values, setValues] = useState<Record<string, string>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    const next: Record<string, string> = {};

    for (const row of rows) {
      next[row.section] = row.percent;
    }

    setValues(next);
    setErrors({});
  }, [rows]);

  const dirty = rows.some(
    (row) => (values[row.section] ?? row.percent) !== row.percent,
  );

  const save = async () => {
    setSaving(true);
    setErrors({});

    const { content, response } = await OrdersApi.saveDiscounts(
      orderId,
      values,
    );

    setSaving(false);

    if (!response.success) {
      const returned = content?.errors ?? {};
      const next: Record<string, string> = {};

      for (const [section, messages] of Object.entries(returned)) {
        if (Array.isArray(messages) && typeof messages[0] === 'string') {
          next[section] = messages[0];
        }
      }

      if (Object.keys(next).length === 0) {
        notifyError(t('api.ise'));
      }

      setErrors(next);

      return;
    }

    onSaved();
  };

  return (
    <section className="ge-section">
      <div className="ge-section__head">
        {t('page.orders.discount.title')}
        <span className="ge-section__end ge-quiet">
          {t('page.orders.discount.level')}
        </span>
      </div>

      {rows.map((row) => {
        const limit = Number(row.max_percent);
        const error = errors[row.section];

        return (
          <div className="ge-disc" key={row.section}>
            <label className="ge-disc__label" htmlFor={`disc-${row.section}`}>
              {t(`page.price_list.section.${row.section}`)}
              <span className="ge-quiet">
                {limit === 0
                  ? t('page.orders.discount.none_allowed')
                  : t('page.orders.discount.limit', {
                      percent: trim(row.max_percent),
                      section: row.price_section ?? '—',
                    })}
              </span>
            </label>

            <span className="ge-disc__field">
              <input
                id={`disc-${row.section}`}
                className={error ? 'is-invalid' : undefined}
                type="number"
                min={0}
                max={limit}
                step="0.5"
                disabled={limit === 0}
                value={values[row.section] ?? ''}
                aria-invalid={error !== undefined}
                onChange={(event) =>
                  setValues((current) => ({
                    ...current,
                    [row.section]: event.target.value,
                  }))
                }
              />
              <span className="ge-disc__unit">%</span>
            </span>

            {error && <div className="ge-disc__error">{error}</div>}
          </div>
        );
      })}

      {dirty && (
        <div className="ge-disc__foot">
          <Button
            variant="contained"
            size="small"
            loading={saving}
            onClick={() => void save()}
          >
            {t('page.orders.discount.save')}
          </Button>
        </div>
      )}
    </section>
  );
}

/** „10.00" czyta się gorzej niż „10" — zero po przecinku nic nie wnosi. */
function trim(value: string) {
  return value.replace(/\.?0+$/, '') || '0';
}
