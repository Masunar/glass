import { redirectRoutes } from '@router/redirect-router';

import { useUserModal } from '../UserModalContext';
import ChangePassword from './ChangePassword';
import type { PasswordFormData } from './ChangePassword';
import EditUser from './EditUser';
import type { EditFormData } from './EditUser';
import ModalHomepage from './ModalHomepage';
import Mfa from './mfa/Mfa';
import { useState } from 'react';
import { useForm } from 'react-hook-form';

import { Div } from '@salvon/components/div';
import { Modal } from '@salvon/components/modal';
import { useTranslation } from '@salvon/hooks/useTranslation';
import {
  notifyError,
  notifySuccess,
  notifyWarning,
} from '@salvon/utils/notify';

import { AuthApi } from '@app/api/AuthApi';
import { useRefreshUser, useUser } from '@app/hook/use-user';

export default function UserModal() {
  const { open, page, mfaRecovered, closeModal, setPage } = useUserModal();
  const t = useTranslation();
  const user = useUser();
  const refreshUser = useRefreshUser();

  const pwForm = useForm<PasswordFormData>();
  const editForm = useForm<EditFormData>();

  const [pwLoading, setPwLoading] = useState(false);
  const [editLoading, setEditLoading] = useState(false);

  const handlePasswordSubmit = async (data: PasswordFormData) => {
    setPwLoading(true);
    const { response } = await AuthApi.changePassword(data);
    setPwLoading(false);
    if (response?.success) {
      notifySuccess(t('page.user_account.change_password.success'));
      pwForm.reset();
      setPage('main');
    } else {
      notifyWarning(
        t('error_heading.validation'),
        t('page.user_account.change_password.invalid_current_password'),
      );
    }
  };

  const handleEditSubmit = async (data: EditFormData) => {
    setEditLoading(true);
    const { response, content } = await AuthApi.updateProfile(data);
    setEditLoading(false);
    if (response?.success) {
      if (content?.data?.email_changed) {
        notifySuccess(t('page.user_account.edit.email_changed_info'));
        window.location.href = redirectRoutes.logout.path;
      } else {
        notifySuccess(t('page.user_account.edit.success'));
        refreshUser();
        setPage('main');
      }
    } else {
      notifyError(t('page.user_account.edit.email_taken'));
    }
  };

  const handleEditOpen = () => {
    if (user) {
      editForm.reset({
        first_name: user.first_name,
        last_name: user.last_name,
        email: user.email,
        phone: user.phone,
      });
    }
    setPage('edit');
  };

  return (
    <Modal
      open={open}
      setOpen={(v) => {
        if (!v) closeModal();
      }}
      closeOnBackdropClick
      closeOnEsc
      closeButton
      maxWidth="sm"
    >
      <Div sx={{ pb: 2 }}>
        {page === 'main' && user && (
          <ModalHomepage
            user={user}
            onPassword={() => setPage('password')}
            onMfa={() => setPage('mfa')}
            onEdit={handleEditOpen}
          />
        )}
        {page === 'password' && (
          <ChangePassword
            form={pwForm}
            onBack={() => setPage('main')}
            onSubmit={handlePasswordSubmit}
            loading={pwLoading}
          />
        )}
        {page === 'mfa' && user && (
          <Mfa
            user={user}
            onBack={() => setPage('main')}
            onRefresh={refreshUser}
            recovered={mfaRecovered}
          />
        )}
        {page === 'edit' && (
          <EditUser
            form={editForm}
            currentEmail={user?.email ?? ''}
            onBack={() => setPage('main')}
            onSubmit={handleEditSubmit}
            loading={editLoading}
          />
        )}
      </Div>
    </Modal>
  );
}
