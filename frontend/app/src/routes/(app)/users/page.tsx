import UserDrawer from './_components/UserDrawer';
import { useEffect, useMemo, useState } from 'react';
import { PiEnvelopeSimple, PiPencilSimple, PiPlus } from 'react-icons/pi';

import { Button } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import {
  type UserBoard,
  type UserGroupKey,
  type UserRow,
  UsersApi,
} from '@app/api/UsersApi';
import HasPermission from '@app/components/HasPermission';
import {
  Band,
  type Column,
  DataList,
  ListHead,
  Row,
  Stage,
  Strip,
  Strips,
} from '@app/components/list';
import { Permission, SubPermission } from '@app/config/permission';

const columns: Column[] = [
  { labelKey: 'page.users.column.user', width: 'minmax(220px, 1fr)' },
  { labelKey: 'email', width: '220px' },
  { labelKey: 'phone', width: '150px' },
  { labelKey: 'page.users.column.last_login', width: '168px' },
  { labelKey: 'role', width: '150px' },
  { labelKey: 'page.users.column.access', width: '104px' },
  { labelKey: 'page.users.column.actions', width: '150px' },
];

type Filter = 'all' | UserGroupKey;

/** „dziś 11:57”, „wczoraj 16:22”, dalej data — nie surowy znacznik czasu. */
function loginLabel(row: UserRow, t: (key: string) => string): string {
  if (row.last_login_at === null) {
    return row.waiting_days === null
      ? t('page.users.never')
      : `${t('page.users.never')} · ${row.waiting_days} d`;
  }

  const at = new Date(row.last_login_at);
  const time = at.toLocaleTimeString('pl-PL', {
    hour: '2-digit',
    minute: '2-digit',
  });
  const days = Math.floor(
    (new Date().setHours(0, 0, 0, 0) - new Date(at).setHours(0, 0, 0, 0)) /
      86400000,
  );

  if (days === 0) {
    return `${t('page.users.today')} ${time}`;
  }

  if (days === 1) {
    return `${t('page.users.yesterday')} ${time}`;
  }

  return at.toLocaleDateString('pl-PL');
}

export default function Page() {
  const t = useTranslation();
  const [board, setBoard] = useState<UserBoard | null>(null);
  const [filter, setFilter] = useState<Filter>('all');
  const [drawer, setDrawer] = useState<{ open: boolean; user: UserRow | null }>(
    { open: false, user: null },
  );

  const load = async () => {
    const { content } = await UsersApi.board();
    const data: UserBoard | undefined = content?.data;

    if (data) {
      setBoard(data);
    }
  };

  useEffect(() => {
    void load();
  }, []);

  const summary = board?.summary;

  const groups = useMemo(
    () =>
      (board?.groups ?? []).filter(
        (group) => filter === 'all' || group.key === filter,
      ),
    [board, filter],
  );

  const invite = async (row: UserRow) => {
    const { content, response } = await UsersApi.invite(row.id);

    if (!response.success) {
      const message = content?.errors?.email?.[0];

      notifyError(message ?? t('api.ise'));
      return;
    }

    notifySuccess(content?.data?.message ?? t('api.save_success'));
    await load();
  };

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">{t('page.module.adm')}</div>
          <h1 className="ge-head__title">{t('page.users.title')}</h1>
          {summary && (
            <div className="ge-panel__meta">
              {t('page.users.meta', {
                total: summary.total,
                roles: summary.roles,
              })}
            </div>
          )}
        </div>

        <div className="ge-head__actions">
          <HasPermission
            permission={Permission.USERS}
            sub={SubPermission.CREATE}
          >
            <Button
              variant="contained"
              icon={<PiPlus />}
              sx={{ visibility: drawer.open ? 'hidden' : 'visible' }}
              onClick={() => setDrawer({ open: true, user: null })}
            >
              {t('page.users.invite')}
            </Button>
          </HasPermission>
        </div>
      </header>

      {summary && (
        <Strips>
          <Strip
            variant="prod"
            label={t('page.users.strip.active')}
            value={summary.active}
            note={t('page.users.strip.logged_today', {
              count: summary.logged_in_today,
            })}
          />
          <Strip
            variant="money"
            label={t('page.users.strip.invited')}
            value={summary.invited}
            note={
              summary.oldest_invite_days === null
                ? t('page.users.strip.no_invites')
                : t('page.users.strip.oldest_invite', {
                    count: summary.oldest_invite_days,
                  })
            }
          />
          <Strip
            variant="module"
            label={t('page.users.strip.roles')}
            value={summary.roles}
            note={summary.role_names.join(', ')}
          />
          <Strip
            variant={summary.stale > 0 ? 'alert' : 'plain'}
            label={t('page.users.strip.attention')}
            value={summary.stale}
            noteWarn={summary.stale > 0}
            note={t('page.users.strip.stale', { days: summary.stale_days })}
          />
        </Strips>
      )}

      <nav className="ge-filters" aria-label={t('page.users.filters')}>
        {(['all', 'active', 'invited', 'disabled'] as Filter[]).map((key) => (
          <button
            key={key}
            type="button"
            className={filter === key ? 'is-active' : ''}
            onClick={() => setFilter(key)}
          >
            {t(`page.users.filter.${key}`)}{' '}
            {summary
              ? key === 'all'
                ? summary.total
                : summary[key === 'active' ? 'active' : key]
              : ''}
          </button>
        ))}
        <span className="ge-filters__end">
          {t('page.users.sorted_by_login')}
        </span>
      </nav>

      <DataList columns={columns}>
        <ListHead columns={columns} translate={t} />

        {groups.map((group) =>
          group.rows.length === 0 ? null : (
            <div key={group.key} style={{ display: 'contents' }}>
              {group.key !== 'active' && (
                <Band
                  variant={group.key === 'invited' ? 'module' : 'plain'}
                  title={`${t(`page.users.band.${group.key}`)} — ${group.rows.length}`}
                  meta={t(`page.users.band.${group.key}_meta`)}
                />
              )}

              {group.rows.map((row) => (
                <Row key={row.id} selected={row.is_self} alert={row.is_stale}>
                  <div className="ge-user">
                    <span
                      className={[
                        'ge-avatar',
                        row.is_self ? 'ge-avatar--me' : '',
                        group.key === 'invited' ? 'ge-avatar--invited' : '',
                      ]
                        .filter(Boolean)
                        .join(' ')}
                    >
                      {row.initials}
                    </span>
                    <div style={{ minWidth: 0 }}>
                      <div className="ge-name">{row.name}</div>
                      <div className="ge-note">
                        {[
                          row.is_self ? t('page.users.you') : null,
                          row.location,
                        ]
                          .filter(Boolean)
                          .join(' · ') || '—'}
                      </div>
                    </div>
                  </div>

                  <div className="ge-cell--wrap">{row.email}</div>
                  <div className={row.phone ? '' : 'ge-muted'}>
                    {row.phone ?? '—'}
                  </div>
                  <div>{loginLabel(row, t)}</div>

                  <Stage
                    label={row.roles.join(', ') || '—'}
                    tone={row.is_active ? 'new' : 'idle'}
                  />

                  <div className="ge-next__text">
                    {t(
                      row.is_active
                        ? row.is_superuser
                          ? 'page.users.access.full'
                          : 'page.users.access.limited'
                        : 'page.users.access.none',
                    )}
                  </div>

                  <Flex gap={1}>
                    <HasPermission
                      permission={Permission.USERS}
                      sub={SubPermission.UPDATE}
                    >
                      <Button
                        variant="text"
                        size="small"
                        icon={<PiPencilSimple />}
                        onClick={() => setDrawer({ open: true, user: row })}
                      >
                        {t('edit')}
                      </Button>
                    </HasPermission>

                    {group.key === 'invited' && (
                      <HasPermission
                        permission={Permission.USERS}
                        sub={SubPermission.UPDATE}
                      >
                        <Button
                          variant="text"
                          size="small"
                          icon={<PiEnvelopeSimple />}
                          onClick={() => void invite(row)}
                        >
                          {t('page.users.send')}
                        </Button>
                      </HasPermission>
                    )}
                  </Flex>
                </Row>
              ))}
            </div>
          ),
        )}
      </DataList>

      <UserDrawer
        user={drawer.user}
        open={drawer.open}
        onClose={() => setDrawer({ open: false, user: null })}
        onSaved={() => {
          setDrawer({ open: false, user: null });
          void load();
        }}
      />
    </>
  );
}
