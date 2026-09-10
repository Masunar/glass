import { useEffect, useRef, useState } from 'react';

import { useTranslation } from '@salvon/hooks/useTranslation';

import { type ContractorRow, ContractorsApi } from '@app/api/ContractorsApi';

type Props = {
  value: ContractorRow | null;
  onPick: (contractor: ContractorRow | null) => void;
  error?: string | null;
};

/**
 * Wybór kontrahenta przez pisanie, nie przez rozwijaną listę.
 *
 * W kartotece jest ich kilka tysięcy, a handlowiec zna nazwę albo NIP
 * z telefonu. Lista rozwijana z taką liczbą pozycji jest nie do
 * przewinięcia i wymusza dokładne pamiętanie pierwszej litery nazwy —
 * a firma bywa zapisana i jako „STECKO MEBLE", i jako „Stecko".
 */
export default function ContractorPicker({ value, onPick, error }: Props) {
  const t = useTranslation();
  const [query, setQuery] = useState('');
  const [rows, setRows] = useState<ContractorRow[]>([]);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(0);
  const box = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const needle = query.trim();

    if (needle.length < 2) {
      setRows([]);
      return;
    }

    // Odpowiedź na porzucone zapytanie nie może nadpisać nowszej listy.
    let current = true;

    const timer = setTimeout(async () => {
      const { content } = await ContractorsApi.search(needle);

      if (current) {
        setRows(content?.data?.contractors ?? []);
        setActive(0);
      }
    }, 220);

    return () => {
      current = false;
      clearTimeout(timer);
    };
  }, [query]);

  useEffect(() => {
    const onClickOutside = (event: MouseEvent) => {
      if (!box.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    };

    document.addEventListener('mousedown', onClickOutside);

    return () => document.removeEventListener('mousedown', onClickOutside);
  }, []);

  const pick = (row: ContractorRow) => {
    onPick(row);
    setQuery('');
    setRows([]);
    setOpen(false);
  };

  if (value) {
    return (
      <div className="ge-uf">
        <span className="ge-uf__label">
          {t('page.orders.form.contractor')}
          <span className="ge-uf__req"> •</span>
        </span>
        <div className="ge-uf__static">
          {value.display_name}
          <button
            type="button"
            className="ge-linkish"
            onClick={() => onPick(null)}
          >
            {t('page.orders.form.change')}
          </button>
          <div className="ge-quiet">
            {[value.tax_id ? `NIP ${value.tax_id}` : null, value.phone]
              .filter(Boolean)
              .join(' · ')}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="ge-pick" ref={box}>
      <label className={error ? 'ge-uf is-invalid' : 'ge-uf'}>
        <span className="ge-uf__label">
          {t('page.orders.form.contractor')}
          <span className="ge-uf__req"> •</span>
        </span>
        <input
          className="ge-uf__input"
          value={query}
          placeholder={t('page.orders.form.contractor_hint')}
          aria-invalid={error !== null && error !== undefined}
          onChange={(event) => {
            setQuery(event.target.value);
            setOpen(true);
          }}
          onFocus={() => setOpen(true)}
          onKeyDown={(event) => {
            if (event.key === 'ArrowDown') {
              event.preventDefault();
              setActive((index) => Math.min(index + 1, rows.length - 1));
            }

            if (event.key === 'ArrowUp') {
              event.preventDefault();
              setActive((index) => Math.max(index - 1, 0));
            }

            if (event.key === 'Enter' && rows[active]) {
              event.preventDefault();
              pick(rows[active]);
            }
          }}
        />
        {error && <span className="ge-uf__error">{error}</span>}
      </label>

      {open && query.trim().length >= 2 && (
        <div className="ge-pick__list">
          {rows.length === 0 ? (
            <div className="ge-pick__empty">
              {t('page.orders.form.contractor_none')}
            </div>
          ) : (
            rows.map((row, index) => (
              <button
                key={row.id}
                type="button"
                className={
                  index === active ? 'ge-pick__item is-active' : 'ge-pick__item'
                }
                onMouseEnter={() => setActive(index)}
                onClick={() => pick(row)}
              >
                <div className="ge-pick__name">{row.display_name}</div>
                <div className="ge-pick__meta">
                  {[row.tax_id ? `NIP ${row.tax_id}` : null, row.phone]
                    .filter(Boolean)
                    .join(' · ') || row.name}
                </div>
              </button>
            ))
          )}
        </div>
      )}
    </div>
  );
}
