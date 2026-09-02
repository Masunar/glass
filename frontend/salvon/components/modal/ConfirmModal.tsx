import { type MouseEvent, type ReactElement, cloneElement } from 'react';

import { Button, type ButtonProps } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import { type ConfirmModalProps, Modal } from '@salvon/components/modal';
import type { ModalElementCallbackProps } from '@salvon/components/modal/types';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function ConfirmModal({
  open,
  setOpen,
  slotProps,
  cancelButton,
  confirmButton,
  maxWidth,
  anchor,
  children,
  onConfirm,
  onCancel,
  closeButton,
  title,
  confirmLabel,
  cancelLabel,
  blur,
  backdrop,
  loading = false,
  autoCloseOnConfirm = false,
  autoCloseOnCancel = true,
}: ConfirmModalProps) {
  const t = useTranslation();

  const { modal, footerWrapper } = slotProps ?? {};

  const defaultCancelButton: ReactElement<ButtonProps> = (
    <Button variant="text" sx={{ minWidth: '60px' }} disabled={loading}>
      {cancelLabel ?? t('cancel')}
    </Button>
  );

  const defaultConfirmButton: ReactElement<ButtonProps> = (
    <Button variant="text" sx={{ minWidth: '60px' }} disabled={loading}>
      {confirmLabel ?? t('confirm')}
    </Button>
  );

  cancelButton = cancelButton ?? defaultCancelButton;
  confirmButton = confirmButton ?? defaultConfirmButton;

  const modalFooter = ({ closeModal }: ModalElementCallbackProps) => (
    <Flex jEnd {...footerWrapper}>
      <div>
        {cloneElement(cancelButton, {
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
      </div>
      <div>
        {cloneElement(confirmButton, {
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
      </div>
    </Flex>
  );

  return (
    <Modal
      loading={loading}
      canCloseOnLoading={false}
      {...modal}
      maxWidth={maxWidth}
      footer={modalFooter}
      open={open}
      setOpen={setOpen}
      anchor={anchor}
      closeButton={closeButton}
      title={title}
      blur={blur}
      backdrop={backdrop}
    >
      {children}
    </Modal>
  );
}
