import FormFields from './FormFields';
import { useEffect, useState } from 'react';

import { IconButton } from '@salvon/components/icon-button';
import type { CellRenderer } from '@salvon/components/legacy/table/Interfaces';
import { FormModal } from '@salvon/components/modal';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import { UsersApi } from '@app/api/UsersApi';
import HasPermission from '@app/components/HasPermission';
import { Permission, SubPermission } from '@app/config/permission';
import { useUser } from '@app/hook/use-user';

export default function Edit({ row, reloadRow }: CellRenderer) {
  const [loading, setLoading] = useState(false);
  const [open, setOpen] = useState(false);
  const t = useTranslation();
  const user = useUser();

  const form = useForm();
  useEffect(() => {
    if (!open) form.reset();
  }, [open]);

  const handleSubmit = async (data: any) => {
    setLoading(true);
    const res = await UsersApi.update(row.id, data);
    setLoading(false);

    if (!validationCompleted(res.content, form.setError, t)) {
      return;
    }

    if (res.response.success) {
      notifySuccess(t('api.save_success'));
      reloadRow();
      setOpen(false);
      return;
    }

    notifyError(t('api.ise'));
  };

  if (row.id === user?.id) {
    return <></>;
  }

  return (
    <HasPermission permission={Permission.USERS} sub={SubPermission.UPDATE}>
      <FormModal
        loading={loading}
        form={form}
        open={open}
        setOpen={setOpen}
        anchor={<IconButton preset="edit" />}
        onSubmit={handleSubmit}
      >
        <FormFields row={row} />
      </FormModal>
    </HasPermission>
  );
}
