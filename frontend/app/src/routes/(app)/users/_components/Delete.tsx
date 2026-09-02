import { useState } from 'react';

import { Div } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';
import type { CellRenderer } from '@salvon/components/legacy/table/Interfaces';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import { UsersApi } from '@app/api/UsersApi';
import HasPermission from '@app/components/HasPermission';
import { Permission, SubPermission } from '@app/config/permission';
import { useUser } from '@app/hook/use-user';

export default function Delete({ row, reloadTable }: CellRenderer) {
  const [loading, setLoading] = useState(false);
  const t = useTranslation();
  const user = useUser();

  if (row.id === user?.id) {
    return <></>;
  }

  return (
    <HasPermission permission={Permission.USERS} sub={SubPermission.DELETE}>
      <IconButton
        preset="delete"
        confirm={{
          slotProps: {
            confirmButton: {
              color: 'error',
            },
          },
          loading,
          label: <Div sx={{ maxWidth: 200 }}>{t('entry_delete_confirm')}</Div>,
          onConfirm: async (closePopover) => {
            setLoading(true);
            const res = await UsersApi.remove(row.id);
            setLoading(false);

            if (!res.response.success) {
              notifyError(t('api.ise'));
              return;
            }

            closePopover();
            notifySuccess(t('api.entry_deleted'));
            reloadTable();
          },
        }}
      />
    </HasPermission>
  );
}
