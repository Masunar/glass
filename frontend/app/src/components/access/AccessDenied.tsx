import { PiLockKey } from 'react-icons/pi';

import { useTranslation } from '@salvon/hooks/useTranslation';

type Props = {
  /** Nazwy uprawnień, których brakuje — dokładnie tak, jak w panelu. */
  missing: string[];
  module: string | null;
};

/**
 * Ekran „brak dostępu".
 *
 * Wcześniej powłoka po cichu odbijała na pulpit. Z punktu widzenia
 * człowieka wyglądało to jak awaria: kliknął i wrócił w to samo
 * miejsce, bez słowa wyjaśnienia. Ekran zostaje na miejscu i mówi
 * **czego brakuje**, nazwą taką samą jak w panelu uprawnień — żeby
 * prośba do administratora brzmiała „dodaj mi `orders.list`", a nie
 * „coś mi nie działa".
 */
export default function AccessDenied({ missing, module }: Props) {
  const t = useTranslation();

  return (
    <div className="ge-denied">
      <div className="ge-denied__mark">
        <PiLockKey />
      </div>

      <h1 className="ge-head__title">{t('page.denied.title')}</h1>

      <p className="ge-quiet">
        {module
          ? t('page.denied.lead_module', { module: t(`page.module.${module}`) })
          : t('page.denied.lead')}
      </p>

      {missing.length > 0 && (
        <div className="ge-denied__list">
          <div className="ge-section__head">{t('page.denied.missing')}</div>
          {missing.map((name) => (
            <code className="ge-denied__item" key={name}>
              {name}
            </code>
          ))}
        </div>
      )}

      <p className="ge-quiet">{t('page.denied.hint')}</p>
    </div>
  );
}
