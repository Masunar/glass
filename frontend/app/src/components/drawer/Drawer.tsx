import { type ReactNode, useEffect, useRef } from 'react';

import { useTranslation } from '@salvon/hooks/useTranslation';

type Props = {
  open: boolean;
  onClose: () => void;
  kicker: string;
  title: string;
  /** Prawa strona nagłówka — przełącznik typu, licznik kroków itp. */
  headExtra?: ReactNode;
  /** Pas pod nagłówkiem: ostrzeżenia, które dotyczą całego formularza. */
  banner?: ReactNode;
  foot?: ReactNode;
  narrow?: boolean;
  children: ReactNode;
};

/**
 * Panel boczny — warstwa nad treścią, nie kolumna obok niej.
 *
 * Lista pod spodem nie przesuwa się ani nie zwęża. Gdyby się zwężała,
 * po zamknięciu panelu oko musiałoby od nowa szukać wiersza, od którego
 * wszystko się zaczęło.
 *
 * Zamknięcie: ✕, przycisk w stopce, tło i Esc — cztery drogi, bo panel
 * bywa otwierany przez pomyłkę i wyjście musi być oczywiste.
 */
export default function Drawer({
  open,
  onClose,
  kicker,
  title,
  headExtra,
  banner,
  foot,
  narrow,
  children,
}: Props) {
  const t = useTranslation();
  const panel = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) {
      return;
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', onKeyDown);

    // Fokus na pierwszym polu: panel otwiera się po to, żeby coś wpisać.
    const first = panel.current?.querySelector<HTMLElement>(
      'input, textarea, select',
    );
    first?.focus();

    return () => document.removeEventListener('keydown', onKeyDown);
  }, [open, onClose]);

  return (
    <>
      <div
        className={open ? 'ge-scrim is-open' : 'ge-scrim'}
        onClick={onClose}
        aria-hidden="true"
      />

      <aside
        ref={panel}
        className={[
          'ge-drawer',
          narrow ? 'ge-drawer--narrow' : '',
          open ? 'is-open' : '',
        ]
          .filter(Boolean)
          .join(' ')}
        aria-label={title}
        aria-hidden={!open}
        // Panel zasunięty zostaje w drzewie, żeby animacja miała co
        // animować, ale nie może zbierać fokusu z klawiatury.
        inert={!open}
      >
        <div className="ge-drawer__head">
          <div>
            <div className="ge-drawer__kicker">{kicker}</div>
            <h2 className="ge-drawer__title">{title}</h2>
          </div>

          <div
            style={{
              marginLeft: 'auto',
              display: 'flex',
              alignItems: 'center',
            }}
          >
            {headExtra}
            <button
              type="button"
              className="ge-drawer__close"
              onClick={onClose}
              aria-label={t('close')}
            >
              ✕
            </button>
          </div>
        </div>

        {banner}

        <div className="ge-drawer__body">{children}</div>

        {foot && <div className="ge-drawer__foot">{foot}</div>}
      </aside>
    </>
  );
}

/** Kolumna panelu — dwie kolumny czyta się szybciej niż jedną długą. */
export function DrawerColumn({ children }: { children: ReactNode }) {
  return <div className="ge-drawer__col">{children}</div>;
}
