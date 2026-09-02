import { useAclScopedRequest } from '../../../../../hook/use-acl-scoped-request';

import { Flex } from '@salvon/components/div';
import { FormControl } from '@salvon/components/form';
import { useTranslation } from '@salvon/hooks/useTranslation';
import {
  requiredEmailRule,
  requiredRule,
} from '@salvon/utils/validation-rules';

import { UsersApi } from '@app/api/UsersApi';
import { translatePermission } from '@app/components/utils/permission';
import { Permission, SubPermission } from '@app/config/permission';

export default function FormFields({ row }: { row?: any }) {
  const roles = useAclScopedRequest(
    () => UsersApi.roles({ limit: -1 }),
    false,
    [{ permission: Permission.ROLES, subPermission: SubPermission.LIST }],
  );
  const t = useTranslation();
  return (
    <Flex column gap={1}>
      <FormControl
        defaultValue={row?.first_name}
        label={t('first_name')}
        variant="text"
        name="first_name"
        rules={requiredRule(t)}
        required
      />
      <FormControl
        defaultValue={row?.last_name}
        label={t('last_name')}
        variant="text"
        name="last_name"
      />
      <FormControl
        defaultValue={row?.email}
        label={t('email')}
        variant="text"
        name="email"
        rules={requiredEmailRule(t)}
        required
      />
      <FormControl
        defaultValue={row?.phone}
        label={t('phone')}
        variant="text"
        name="phone"
      />
      <FormControl
        defaultValue={(row?.roles ?? []).map((r: any) => r.id)}
        label={t('role')}
        variant="select"
        name="roles"
        rules={requiredRule(t)}
        required
        loading={roles.loading}
        disabled={roles.loading || !roles.allowed}
        helperText={
          !roles.allowed
            ? t('no_permission', {
                permission: translatePermission(
                  t,
                  Permission.ROLES,
                  SubPermission.LIST,
                ),
              })
            : undefined
        }
        multiple
        options={(roles.items ?? []).map((r: any) => ({
          label: r.name,
          value: r.id,
        }))}
      />
      <FormControl
        defaultValue={row?.is_active ?? true}
        label={t('is_active')}
        variant="switch"
        name="is_active"
        required
      />
    </Flex>
  );
}
