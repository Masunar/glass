import { type MouseEvent, type ReactElement, cloneElement } from 'react';

import { Button, type ButtonProps } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import { Form } from '@salvon/components/form';
import { type FormModalProps, Modal } from '@salvon/components/modal';
import type { ModalElementCallbackProps } from '@salvon/components/modal/types';
import { isFunction } from '@salvon/utils/type-check';

export default function FormModal({
  onSubmit,
  form,
  open,
  setOpen,
  slotProps,
  cancelButton,
  submitButton,
  maxWidth,
  anchor,
  children,
  onCancel,
  title,
  submitLabel,
  cancelLabel,
  blur,
  backdrop,
  footer,
  useFormProps,
  onValidationFailed,
  closeButton = true,
  loading = false,
  autoCloseOnCancel = true,
  keepMounted = false,
  loadingButtonMask = 'loader',
}: FormModalProps) {
  const { modal, footerWrapper } = slotProps ?? {};

  const defaultCancelButton: ReactElement<ButtonProps> = (
    <Button preset="cancel">{cancelLabel}</Button>
  );
  const defaultSubmitButton: ReactElement<ButtonProps> = (
    <Button preset="save">{submitLabel}</Button>
  );

  cancelButton = cancelButton ?? defaultCancelButton;

  submitButton = submitButton ?? defaultSubmitButton;

  const sharedFooterButtonProps =
    loadingButtonMask === 'disabled' ? { disabled: loading } : { loading };

  const renderFooter = ({ closeModal }: ModalElementCallbackProps) => {
    if (footer === false) {
      return <></>;
    }

    if (isFunction(footer)) {
      return footer({ closeModal });
    }

    if (footer) {
      return footer;
    }

    return (
      <Flex justify="end" gap="10px" mt="30px" {...footerWrapper}>
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
          {submitButton &&
            cloneElement(submitButton, {
              ...sharedFooterButtonProps,
              type: 'submit',
            })}
        </Flex>
      </Flex>
    );
  };

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
      keepMounted={keepMounted}
    >
      {(props) => (
        <Form
          onSubmit={onSubmit}
          form={form}
          useFormProps={useFormProps}
          onValidationFailed={onValidationFailed}
        >
          {isFunction(children) ? children(props) : children}
          {renderFooter(props)}
        </Form>
      )}
    </Modal>
  );
}
