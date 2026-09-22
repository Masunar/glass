import { useEffect, useState } from 'react';
import { PiWarningCircle } from 'react-icons/pi';
import { Link } from 'react-router';

import { useTranslation } from '@salvon/hooks/useTranslation';

import type { RolesBoard } from '@app/api/AccessApi';
import { AccessApi } from '@app/api/AccessApi';

/**
 * Role i stan konfiguracji uprawnień.
 *
 * Wiersz roli niesie **liczbę problemów**, żeby nie trzeba było wchodzić
 * w każdą z siedmiu, aby sprawdzić, czy coś jest nie tak. Pod listą
 * stoją problemy niezależne od roli — w tym uprawnienia, których żaden
 * ekran nie sprawdza.
 */
export default function Page() {
  const t = useTranslation();
  const [board, setBoard] = useState<RolesBoard | null>(null);

  useEffect(() => {
    void (async () => {
      const { content } = await AccessApi.roles();
      const data: RolesBoard | undefined = content?.data;

      if (data) {
        setBoard(data);
      }
    })();
  }, []);

  if (!board) {
    return <div className="ge-empty">{t('page.orders.card.loading')}</div>;
  }

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">{t('page.menu.access')}</div>
          <h1 className="ge-head__title">
            {t('page.access.role_count', { count: board.roles.length })}
          </h1>
          <div className="ge-quiet">{t('page.access.lead')}</div>
        </div>
      </header>

      <section className="ge-section">
        <div className="ge-acc__head">
          <span>{t('page.access.role')}</span>
          <span className="r">{t('page.access.permissions')}</span>
          <span className="r">{t('page.access.packages')}</span>
          <span>{t('page.access.state')}</span>
        </div>

        {board.roles.map((row) => (
          <div className="ge-acc__row" key={row.id}>
            <span>
              <Link to={`/access/roles/${row.id}`}>
                <strong>{row.name}</strong>
              </Link>
            </span>
            <span className="r">
              {row.is_superuser ? t('page.access.all') : row.permissions}
            </span>
            <span className="r">{row.packages > 0 ? row.packages : '—'}</span>
            <span>
              {row.is_superuser ? (
                /* Rola nadrzedna omija sprawdzanie przez Gate::before,
                   wiec jej konfiguracja bylaby teatrem. */
                <span className="ge-tag ge-tag--mod">
                  {t('page.access.superuser')}
                </span>
              ) : row.issues > 0 ? (
                <span className="ge-note ge-note--warn">
                  <PiWarningCircle />{' '}
                  {t('page.access.issue_count', { count: row.issues })}
                </span>
              ) : (
                <span className="ge-quiet">{t('page.access.ok')}</span>
              )}
            </span>
          </div>
        ))}
      </section>

      {board.system.length > 0 && (
        <section className="ge-section">
          <div className="ge-section__head">{t('page.access.system')}</div>
          <div className="ge-quiet">{t('page.access.system_note')}</div>

          {board.system.map((issue, index) => (
            <div className="ge-acc__issue" key={`${issue.kind}-${index}`}>
              <span className="ge-acc__issue-label">{issue.label}</span>
              <span className="ge-quiet">{issue.detail}</span>
            </div>
          ))}
        </section>
      )}
    </>
  );
}
