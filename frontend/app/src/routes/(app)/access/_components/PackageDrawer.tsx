import { useEffect, useState } from 'react';
import { PiTrash, PiWarningCircle } from 'react-icons/pi';

import { Button } from '@salvon/components/button';
import { Form } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type { PackageBoard } from '@app/api/AccessApi';
import { AccessApi } from '@app/api/AccessApi';
import PermissionTree from '@app/components/access/PermissionTree';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Field from '@app/components/drawer/Field';
import Fieldset, { FieldNote, FieldRow } from '@app/components/drawer/Fieldset';

type Props = {
  /** `null` — nowa paczka; liczba — edycja istniejącej. */
  id: number | null;
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
};

/**
 * Edytor paczki.
 *
 * Paczka jest **wiązaniem, nie szablonem**: role jej nie kopiują, tylko
 * ją mają. Jeden zapis zmienia więc dostęp wszystkim rolom naraz i to
 * jest jedyna operacja w tym module, która działa na kogoś, kogo nie
 * widać na ekranie. Dlatego zasięg stoi w pasie nad formularzem —
 * **przed** kliknięciem, nie w bilansie po fakcie.
 */
export default function PackageDrawer({ id, open, onClose, onSaved }: Props) {
  const t = useTranslation();
  const form = useForm();
  const [board, setBoard] = useState<PackageBoard | null>(null);
  const [checked, setChecked] = useState<Set<string>>(new Set());
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!open) {
      return;
    }

    void (async () => {
      setBoard(null);

      const { content } = await AccessApi.package(id);
      const data: PackageBoard | undefined = content?.data;

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
      form.reset({
        name: data.package?.name ?? '',
        description: data.package?.description ?? '',
      });
    })();
  }, [open, id]);

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

  const submit = async (data: any) => {
    setBusy(true);

    const { content, response } = await AccessApi.savePackage(id, {
      name: String(data.name ?? ''),
      description: data.description ? String(data.description) : null,
      permissions: [...checked],
    });

    setBusy(false);

    if (!validationCompleted(content, form.setError, t)) {
      return;
    }

    if (!response.success) {
      notifyError(content?.errors?.permissions?.[0] ?? t('api.ise'));
      return;
    }

    const balance = content?.data?.balance;

    notifySuccess(
      t('page.access.package_saved', {
        granted: balance?.granted ?? 0,
        added: balance?.added ?? 0,
        removed: balance?.removed ?? 0,
        roles: balance?.roles ?? 0,
      }),
    );

    onSaved();
  };

  const remove = async () => {
    if (id === null) {
      return;
    }

    setBusy(true);
    const { content, response } = await AccessApi.deletePackage(id);
    setBusy(false);

    if (!response.success) {
      // Paczka uzywana przez role nie znika po cichu — serwis odmawia
      // i podaje powod, ekran ma go powtorzyc, a nie zjesc.
      notifyError(content?.errors?.package?.[0] ?? t('api.ise'));
      return;
    }

    notifySuccess(t('page.access.package_deleted'));
    onSaved();
  };

  const scope = board?.package?.roles ?? [];

  return (
    <Drawer
      open={open}
      onClose={onClose}
      kicker={t('page.access.packages')}
      title={
        board?.package?.name ??
        t(id === null ? 'page.access.package_new' : 'page.access.package_edit')
      }
      banner={
        scope.length > 0 ? (
          <div className="ge-note ge-note--warn ge-acc__banner">
            <PiWarningCircle />{' '}
            {t('page.access.package_reach', { count: scope.length })}:{' '}
            {scope.join(', ')}
          </div>
        ) : undefined
      }
      foot={
        <>
          {id !== null && (
            <Button
              variant="text"
              icon={<PiTrash />}
              disabled={busy}
              onClick={() => void remove()}
            >
              {t('delete')}
            </Button>
          )}
          <div className="ge-drawer__foot-end">
            <Button variant="text" onClick={onClose}>
              {t('cancel')}
            </Button>
            <Button
              variant="contained"
              loading={busy}
              onClick={() => void form.handleSubmit(submit)()}
            >
              {t('save')}
            </Button>
          </div>
        </>
      }
    >
      {!board ? (
        <div className="ge-empty">{t('page.orders.card.loading')}</div>
      ) : (
        <Form form={form} onSubmit={submit}>
          <DrawerColumn>
            <Fieldset tone="ident" label={t('page.access.package_name')}>
              <FieldRow columns="1fr">
                <Field name="name" label={t('name')} required />
              </FieldRow>
              <FieldRow columns="1fr" paddingTop={12}>
                <Field name="description" label={t('description')} />
              </FieldRow>
              <FieldNote>{t('page.access.package_hint')}</FieldNote>
            </Fieldset>

            <PermissionTree
              modules={board.modules}
              groups={board.groups}
              checked={checked}
              onToggle={toggle}
              onToggleGroup={toggleGroup}
            />
          </DrawerColumn>
        </Form>
      )}
    </Drawer>
  );
}
