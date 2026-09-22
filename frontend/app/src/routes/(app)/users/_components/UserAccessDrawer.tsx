import { useEffect, useMemo, useState } from 'react';

import { Button } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type { UserAccessBoard } from '@app/api/AccessApi';
import { AccessApi } from '@app/api/AccessApi';
import PermissionTree from '@app/components/access/PermissionTree';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';

type Props = {
  userId: number | null;
  open: boolean;
  onClose: () => void;
};

/**
 * Uprawnienia użytkownika ponad rolę.
 *
 * **Suma, nie różnica** (U-05): tu się dokłada, nie odbiera. To, co
 * daje rola, przychodzi zaznaczone i zablokowane razem z pochodzeniem
 * — odznaczalne sugerowałoby odbieranie, którego ten model nie robi,
 * a odbieranie przy użytkowniku dałoby stan, w którym o dostępie
 * decyduje kolejność czytania reguł.
 *
 * Panel istnieje po to, żeby nie powstawała dziewiąta rola „Starszy
 * handlowiec 2" za każdym razem, gdy jednej osobie trzeba dołożyć
 * jedno uprawnienie.
 */
export default function UserAccessDrawer({ userId, open, onClose }: Props) {
  const t = useTranslation();
  const [board, setBoard] = useState<UserAccessBoard | null>(null);
  const [checked, setChecked] = useState<Set<string>>(new Set());
  const [busy, setBusy] = useState(false);

  const load = async () => {
    if (userId === null) {
      return;
    }

    setBoard(null);

    const { content } = await AccessApi.user(userId);
    const data: UserAccessBoard | undefined = content?.data;

    if (!data) {
      return;
    }

    setBoard(data);
    setChecked(
      new Set([
        ...data.modules
          .filter((m) => m.has_access)
          .map((m) => m.access_permission),
        ...data.groups.flatMap((g) =>
          g.items.filter((i) => i.granted).map((i) => i.name),
        ),
      ]),
    );
  };

  useEffect(() => {
    if (!open) {
      return;
    }

    void load();
  }, [open, userId]);

  /** Co daje rola — widoczne, ale nie do ruszenia w tym panelu. */
  const locked = useMemo(() => {
    const direct = new Set(board?.direct ?? []);
    const names = new Set<string>();

    for (const module of board?.modules ?? []) {
      if (module.has_access && !direct.has(module.access_permission)) {
        names.add(module.access_permission);
      }
    }

    for (const group of board?.groups ?? []) {
      for (const item of group.items) {
        if (item.granted && !direct.has(item.name)) {
          names.add(item.name);
        }
      }
    }

    return names;
  }, [board]);

  const toggle = (name: string) => {
    const next = new Set(checked);

    if (next.has(name)) {
      next.delete(name);
    } else {
      next.add(name);
    }

    setChecked(next);
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
    if (userId === null) {
      return;
    }

    setBusy(true);

    // Wysylamy wylacznie nadania wprost. To, co daje rola, zostaje
    // przy roli — zapisane przy uzytkowniku przezyloby jej odebranie.
    const direct = [...checked].filter((name) => !locked.has(name));
    const { content, response } = await AccessApi.saveUser(userId, direct);

    setBusy(false);

    if (!response.success) {
      notifyError(
        content?.errors?.user?.[0] ??
          content?.errors?.permissions?.[0] ??
          t('api.ise'),
      );

      return;
    }

    const balance = content?.data?.balance;

    notifySuccess(
      t('page.users.access_saved', {
        granted: balance?.granted ?? 0,
        added: balance?.added ?? 0,
        removed: balance?.removed ?? 0,
      }),
    );

    await load();
  };

  const superuser = board?.user.is_superuser ?? false;

  return (
    <Drawer
      open={open}
      onClose={onClose}
      kicker={t('page.users.access_kicker')}
      title={board?.user.name ?? t('page.users.access_title')}
      banner={
        board && (superuser || board.user.roles.length > 0) ? (
          <div
            className={
              superuser
                ? 'ge-note ge-note--warn ge-acc__banner'
                : 'ge-acc__banner ge-quiet'
            }
          >
            {superuser
              ? t('page.users.access_superuser')
              : `${t('role')}: ${board.user.roles.join(', ')}`}
          </div>
        ) : undefined
      }
      foot={
        <>
          <span className="ge-drawer__foot-note">
            {t('page.users.access_note')}
          </span>
          <div className="ge-drawer__foot-end">
            <Button variant="text" onClick={onClose}>
              {t('close')}
            </Button>
            {!superuser && (
              <Button
                variant="contained"
                loading={busy}
                onClick={() => void save()}
              >
                {t('save')}
              </Button>
            )}
          </div>
        </>
      }
    >
      {!board ? (
        <div className="ge-empty">{t('page.orders.card.loading')}</div>
      ) : (
        <DrawerColumn>
          <PermissionTree
            modules={board.modules}
            groups={board.groups}
            checked={checked}
            locked={locked}
            disabled={superuser}
            onToggle={toggle}
            onToggleGroup={toggleGroup}
          />
        </DrawerColumn>
      )}
    </Drawer>
  );
}
