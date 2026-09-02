import { Tooltip } from '@mui/material';

import FormFields from './FormFields';
import { useEffect, useState } from 'react';

import { Button, type ButtonProps } from '@salvon/components/button';
import { FormModal } from '@salvon/components/modal';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import { UsersApi } from '@app/api/UsersApi';
import HasPermission from '@app/components/HasPermission';
import { translatePermission } from '@app/components/utils/permission';
import { Permission, SubPermission } from '@app/config/permission';
import { useMissingPermissions } from '@app/hook/use-permissions';

type Props = {
  reload: () => void;
};

function SubmitButton({
  title,
  ...buttonProps
}: ButtonProps & { title: string }) {
  return (
    <Tooltip title={title}>
      <span style={{ display: 'inline-flex' }}>
        <Button preset="save" {...buttonProps} />
      </span>
    </Tooltip>
  );
}

export default function Add({ reload }: Props) {
  const [loading, setLoading] = useState(false);
  const [open, setOpen] = useState(false);
  const t = useTranslation();

  const missing = useMissingPermissions()([
    { permission: Permission.ROLES, subPermission: SubPermission.LIST },
  ]);
  const missingLabels = missing.map((name) => {
    const [entity, action] = name.split('.');
    return translatePermission(t, entity, action);
  });

  const form = useForm();
  useEffect(() => {
    if (!open) form.reset();
  }, [open]);

  const handleSubmit = async (data: any) => {
    setLoading(true);
    const res = await UsersApi.create(data);
    setLoading(false);

    if (!validationCompleted(res.content, form.setError, t)) {
      return;
    }

    if (res.response.success) {
      notifySuccess(t('api.save_success'));
      reload();
      setOpen(false);
      return;
    }

    notifyError(t('api.ise'));
  };

  return (
    <HasPermission permission={Permission.USERS} sub={SubPermission.CREATE}>
      <FormModal
        loading={loading}
        form={form}
        open={open}
        setOpen={setOpen}
        anchor={<Button preset="add" />}
        onSubmit={handleSubmit}
        submitButton={
          <SubmitButton
            disabled={missing.length > 0}
            title={
              missing.length > 0
                ? t('missing_permission', {
                    count: missing.length,
                    permissions: missingLabels.join(', '),
                  })
                : ''
            }
          />
        }
      >
        <FormFields />
      </FormModal>
    </HasPermission>
  );
}
