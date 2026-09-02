import { type MouseEvent, type ReactElement, cloneElement } from 'react';

import { Button, type ButtonProps } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import { type ActionModalProps, Modal } from '@salvon/components/modal';
import type { ModalElementCallbackProps } from '@salvon/components/modal/types';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { isFunction } from '@salvon/utils/type-check';

export default function ActionModal({
  variant,
  open,
  setOpen,
  slotProps,
  cancelButton,
  confirmButton,
  deleteButton,
  maxWidth,
  anchor,
  children,
  onConfirm,
  onCancel,
  onDelete,
  title,
  confirmLabel,
  cancelLabel,
  deleteLabel,
  blur,
  backdrop,
  closeButton = true,
  loading = false,
  autoCloseOnConfirm = false,
  autoCloseOnCancel = true,
  autoCloseOnDelete = false,
  loadingButtonMask = 'loader',
}: ActionModalProps) {
  const t = useTranslation();
  const { modal, footerWrapper } = slotProps ?? {};

  const defaultCancelButton: ReactElement<ButtonProps> = (
    <Button preset="cancel">
      {cancelLabel ?? (variant === 'info' ? t('close') : undefined)}
    </Button>
  );
  const defaultConfirmButton: ReactElement<ButtonProps> = (
    <Button preset="save">{confirmLabel}</Button>
  );
  const defaultDeleteButton: ReactElement<ButtonProps> = (
    <Button preset="delete">{deleteLabel}</Button>
  );

  cancelButton =
    cancelButton === false ? undefined : (cancelButton ?? defaultCancelButton);

  confirmButton =
    confirmButton === false
      ? undefined
      : (confirmButton ?? defaultConfirmButton);

  deleteButton =
    deleteButton === false ? undefined : (deleteButton ?? defaultDeleteButton);

  const sharedFooterButtonProps =
    loadingButtonMask === 'disabled' ? { disabled: loading } : { loading };

  if (variant === 'confirm') {
    deleteButton = undefined;
  }

  if (variant === 'delete') {
    confirmButton = undefined;
  }

  if (variant === 'info') {
    confirmButton = undefined;
    deleteButton = undefined;
  }

  const modalFooter = ({ closeModal }: ModalElementCallbackProps) => (
    <Flex
      justify={deleteButton ? 'space-between' : 'end'}
      gap="10px"
      mt="30px"
      {...footerWrapper}
    >
      <Flex gap="10px">
        {deleteButton &&
          cloneElement(deleteButton, {
            ...sharedFooterButtonProps,
            onClick: (e: MouseEvent<HTMLElement>) => {
              if (loading) {
                return;
              }

              if (onDelete) {
                onDelete({ event: e, closeModal });
                return;
              }

              if (autoCloseOnDelete) {
                closeModal();
              }
            },
          })}
      </Flex>
      <Flex gap="10px">
        {cancelButton &&
          cloneElement(cancelButton, {
            ...sharedFooterButtonProps,
            onClick: (e: MouseEvent<HTMLElement>) => {
              if (loading) {
                return;
              }

              if (onCancel) {
                onCancel({ event: e, closeModal });
              }

              if (autoCloseOnCancel) {
                closeModal();
              }
            },
          })}
        {confirmButton &&
          cloneElement(confirmButton, {
            ...sharedFooterButtonProps,
            onClick: (e: MouseEvent<HTMLElement>) => {
              if (loading) {
                return;
              }

              if (onConfirm) {
                onConfirm({ event: e, closeModal });
              }

              if (autoCloseOnConfirm) {
                closeModal();
              }
            },
          })}
      </Flex>
    </Flex>
  );

  return (
    <Modal
      loading={loading}
      canCloseOnLoading={false}
      closeOnEsc={true}
      {...modal}
      maxWidth={maxWidth}
      open={open}
      setOpen={setOpen}
      anchor={anchor}
      closeButton={closeButton}
      title={title}
      blur={blur}
      backdrop={backdrop}
    >
      {(props) => (
        <>
          {isFunction(children) ? children(props) : children}
          {modalFooter(props)}
        </>
      )}
    </Modal>
  );
}
