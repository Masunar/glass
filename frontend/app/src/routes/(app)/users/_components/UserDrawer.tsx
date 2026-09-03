import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { Form, FormControl } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import { type UserRow, UsersApi } from '@app/api/UsersApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Field, { Toggle } from '@app/components/drawer/Field';
import Fieldset, { FieldNote, FieldRow } from '@app/components/drawer/Fieldset';
import { translatePermission } from '@app/components/utils/permission';
import { Permission, SubPermission } from '@app/config/permission';
import { useAclScopedRequest } from '@app/hook/use-acl-scoped-request';

type Props = {
  user: UserRow | null;
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
};

export default function UserDrawer({ user, open, onClose, onSaved }: Props) {
  const t = useTranslation();
  const form = useForm();
  const [saving, setSaving] = useState(false);

  const roles = useAclScopedRequest(
    () => UsersApi.roles({ limit: -1 }),
    false,
    [{ permission: Permission.ROLES, subPermission: SubPermission.LIST }],
  );

  const roleOptions = (roles.items ?? []).map((role: any) => ({
    label: role.name,
    value: role.id,
  }));

  useEffect(() => {
    if (!open) {
      return;
    }

    const [first, ...rest] = (user?.name ?? '').split(' ');

    form.reset({
      first_name: first ?? '',
      last_name: rest.join(' '),
      email: user?.email ?? '',
      phone: user?.phone ?? '',
      roles: [],
      is_active: user?.is_active ?? true,
    });
  }, [open, user?.id]);

  const submit = async (data: any) => {
    setSaving(true);

    const { content, response } = user
      ? await UsersApi.update(user.id, data)
      : await UsersApi.create(data);

    setSaving(false);

    if (!validationCompleted(content, form.setError, t)) {
      return;
    }

    if (!response.success) {
      notifyError(t('api.ise'));
      return;
    }

    notifySuccess(t('api.save_success'));
    onSaved();
  };

  return (
    <Drawer
      narrow
      open={open}
      onClose={onClose}
      kicker={t('page.users.title')}
      title={t(user ? 'page.users.edit' : 'page.users.invite')}
      foot={
        <>
          <span className="ge-drawer__foot-note">
            {t('page.users.password_note')}
          </span>
          <div className="ge-drawer__foot-end">
            <Button variant="text" onClick={onClose}>
              {t('cancel')}
            </Button>
            <Button
              variant="contained"
              loading={saving}
              onClick={() => void form.handleSubmit(submit)()}
            >
              {t('save')}
            </Button>
          </div>
        </>
      }
    >
      <Form form={form} onSubmit={submit}>
        <DrawerColumn>
          <Fieldset tone="ident" label={t('page.users.section.person')}>
            <FieldRow columns="1fr 1fr">
              <Field name="first_name" label={t('first_name')} required />
              <Field name="last_name" label={t('last_name')} />
            </FieldRow>
            <FieldRow columns="1fr" paddingTop={12}>
              <Field name="email" label={t('email')} required type="email" />
            </FieldRow>
            <FieldRow columns="1fr" paddingTop={12}>
              <Field name="phone" label={t('phone')} />
            </FieldRow>
          </Fieldset>

          <Fieldset tone="terms" label={t('page.users.section.access')}>
            <FormControl
              variant="select"
              name="roles"
              label={t('role')}
              multiple
              required
              loading={roles.loading}
              disabled={roles.loading || !roles.allowed}
              helperText={
                roles.allowed
                  ? undefined
                  : t('no_permission', {
                      permission: translatePermission(
                        t,
                        Permission.ROLES,
                        SubPermission.LIST,
                      ),
                    })
              }
              options={roleOptions}
            />
            <div style={{ paddingTop: 12 }}>
              <Toggle name="is_active" label={t('is_active')} />
            </div>
            <FieldNote>{t('page.users.is_active_hint')}</FieldNote>
          </Fieldset>
        </DrawerColumn>
      </Form>
    </Drawer>
  );
}
