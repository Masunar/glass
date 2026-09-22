import { useEffect, useMemo, useState } from 'react';
import { PiArrowLeft, PiWarningCircle } from 'react-icons/pi';
import { Link, useParams } from 'react-router';

import { Button } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type { RoleBoard } from '@app/api/AccessApi';
import { AccessApi } from '@app/api/AccessApi';

/**
 * Konfiguracja roli.
 *
 * Trzy warstwy ze wzorca: moduły z licznikiem pokrycia, strony, grupy
 * uprawnień per zasób. Licznik jest treścią — „6 z 18" mówi w jednym
 * spojrzeniu to, czego osiemnaście przełączników nie powie wcale.
 *
 * Stopka podaje **bilans**, nie „zapisano": po operacji na uprawnieniach
 * chce się wiedzieć dokładnie, co doszło i co zniknęło.
 */
export default function Page() {
  const t = useTranslation();
  const params = useParams();
  const id = Number(params.id);

  const [board, setBoard] = useState<RoleBoard | null>(null);
  const [checked, setChecked] = useState<Set<string>>(new Set());
  const [packages, setPackages] = useState<Set<number>>(new Set());
  const [busy, setBusy] = useState(false);

  const load = async () => {
    const { content } = await AccessApi.role(id);
    const data: RoleBoard | undefined = content?.data;

    if (!data) {
      return;
    }

    setBoard(data);
    setChecked(
      new Set([
        ...data.modules.filter((m) => m.has_access).map((m) => m.access_permission),
        ...data.groups.flatMap((g) =>
          g.items.filter((i) => i.granted).map((i) => i.name),
        ),
      ]),
    );
    setPackages(new Set(data.packages.filter((p) => p.attached).map((p) => p.id)));
  };

  useEffect(() => {
    void load();
  }, [id]);

  /** Co przyjdzie z paczki, a czego nie da się odznaczyć wprost. */
  const fromPackages = useMemo(() => {
    const names = new Set<string>();

    for (const group of board?.groups ?? []) {
      for (const item of group.items) {
        if (item.origin?.includes('paczka')) {
          names.add(item.name);
        }
      }
    }

    return names;
  }, [board]);

  if (!board) {
    return <div className="ge-empty">{t('page.orders.card.loading')}</div>;
  }

  const toggle = (name: string) => {
    const next = new Set(checked);

    if (next.has(name)) {
      next.delete(name);
    } else {
      next.add(name);
    }

    setChecked(next);
  };

  const togglePackage = (packageId: number) => {
    const next = new Set(packages);

    if (next.has(packageId)) {
      next.delete(packageId);
    } else {
      next.add(packageId);
    }

    setPackages(next);
  };

  const toggleGroup = (names: string[], on: boolean) => {
    const next = new Set(checked);

    for (const name of names) {
      if (on) {
        next.add(name);
      } else {
        next.delete(name);
      }
    }

    setChecked(next);
  };

  const save = async () => {
    setBusy(true);

    const { content, response } = await AccessApi.saveRole(
      id,
      [...checked],
      [...packages],
    );

    setBusy(false);

    if (!response.success) {
      notifyError(
        content?.errors?.role?.[0] ??
          content?.errors?.permissions?.[0] ??
          t('api.ise'),
      );

      return;
    }

    const balance = content?.data?.balance;

    // Bilans zamiast „zapisano" — to najlepszy pomysl wzorca.
    notifySuccess(
      t('page.access.saved', {
        granted: balance?.granted ?? 0,
        added: balance?.added ?? 0,
        removed: balance?.removed ?? 0,
      }),
    );

    await load();
  };

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">
            <Link to="/access">
              <PiArrowLeft /> {t('page.menu.access')}
            </Link>
          </div>
          <h1 className="ge-head__title">{board.role.name}</h1>
        </div>

        {!board.role.is_superuser && (
          <div className="ge-head__actions">
            <Button variant="contained" loading={busy} onClick={() => void save()}>
              {t('save')}
            </Button>
          </div>
        )}
      </header>

      {board.role.is_superuser && (
        <div className="ge-note ge-note--warn ge-acc__banner">
          {t('page.access.superuser_note')}
        </div>
      )}

      {board.issues.length > 0 && (
        <section className="ge-section">
          <div className="ge-section__head">{t('page.access.issues')}</div>
          {board.issues.map((issue, index) => (
            <div className="ge-note ge-note--warn" key={index}>
              <PiWarningCircle /> {issue.label}: {issue.detail}
            </div>
          ))}
        </section>
      )}

      <div className="ge-card">
        <div className="ge-card__main">
          <section className="ge-section">
            <div className="ge-section__head ge-section__head--strong">
              {t('page.access.modules')}
            </div>
            <div className="ge-quiet">{t('page.access.modules_note')}</div>

            <div className="ge-acc__tiles">
              {board.modules.map((module) => (
                <label className="ge-acc__tile" key={module.key}>
                  <span className="ge-acc__tile-head">
                    <input
                      type="checkbox"
                      disabled={board.role.is_superuser}
                      checked={checked.has(module.access_permission)}
                      onChange={() => toggle(module.access_permission)}
                    />
                    <strong>{module.label}</strong>
                  </span>
                  {/* Licznik pokrycia zamiast osiemnastu przelacznikow:
                      „6 z 18" mowi to samo w jednym spojrzeniu. */}
                  <span className="ge-quiet">
                    {t('page.access.pages_covered', {
                      covered: module.pages_covered,
                      total: module.pages,
                    })}
                  </span>
                  {module.access_origin && (
                    <span className="ge-quiet">{module.access_origin}</span>
                  )}
                </label>
              ))}
            </div>
          </section>

          {board.groups.map((group) => (
            <section className="ge-section" key={group.key}>
              <div className="ge-section__head">
                {group.label}
                <span className="ge-section__end ge-quiet">
                  <code>{group.key}</code> · {group.granted}/{group.total}
                  {!board.role.is_superuser && (
                    <button
                      type="button"
                      className="ge-acc__all"
                      onClick={() =>
                        toggleGroup(
                          group.items.map((i) => i.name),
                          group.granted < group.total,
                        )
                      }
                    >
                      {group.granted < group.total
                        ? t('page.access.check_all')
                        : t('page.access.uncheck_all')}
                    </button>
                  )}
                </span>
              </div>

              {/* Uprawnienie zaplanowane zostaje widoczne z powodem —
                  ukrycie go zamienialoby przeoczenie w tajemnice. */}
              {group.state === 'planned' && (
                <div className="ge-note ge-note--warn">
                  {t('page.access.planned')}: {group.note}
                </div>
              )}

              {group.items.map((item) => (
                <label className="ge-acc__item" key={item.name}>
                  <input
                    type="checkbox"
                    disabled={board.role.is_superuser || fromPackages.has(item.name)}
                    checked={checked.has(item.name)}
                    onChange={() => toggle(item.name)}
                  />
                  <span>{t(`page.access.sub.${item.sub}`)}</span>
                  <code className="ge-quiet">{item.name}</code>
                  {item.origin && (
                    <span className="ge-quiet ge-acc__origin">{item.origin}</span>
                  )}
                </label>
              ))}
            </section>
          ))}
        </div>

        <aside className="ge-card__side ge-card__side--right">
          <section className="ge-section">
            <div className="ge-section__head">{t('page.access.packages')}</div>
            <div className="ge-quiet">{t('page.access.packages_note')}</div>

            {board.packages.length === 0 && (
              <div className="ge-quiet">{t('page.access.packages_empty')}</div>
            )}

            {board.packages.map((row) => (
              <label className="ge-acc__item" key={row.id}>
                <input
                  type="checkbox"
                  disabled={board.role.is_superuser}
                  checked={packages.has(row.id)}
                  onChange={() => togglePackage(row.id)}
                />
                <span>{row.name}</span>
                {/* Zasieg przy paczce: zmiana dotknie tyle rol. */}
                <span className="ge-quiet">
                  {t('page.access.package_scope', {
                    permissions: row.permissions,
                    roles: row.roles,
                  })}
                </span>
              </label>
            ))}
          </section>

          <section className="ge-section">
            <div className="ge-section__head">{t('page.access.pages')}</div>
            {board.pages.map((page) => (
              <div className="ge-acc__page" key={page.code}>
                <span className={page.is_open ? '' : 'ge-quiet'}>
                  {page.label}
                </span>
                <code className="ge-quiet">{page.path}</code>
                {page.is_public && (
                  <span className="ge-quiet">{t('page.access.public')}</span>
                )}
              </div>
            ))}
          </section>
        </aside>
      </div>
    </>
  );
}
