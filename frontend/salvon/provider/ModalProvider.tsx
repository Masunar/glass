import { type ReactNode, useState } from 'react';
import { createContext } from 'react';

import { Modal, type ModalProps } from '@salvon/components/modal';
import type { Noop } from '@salvon/types';

export type ModalConfig = {
  content: ReactNode;
  onClose?: () => void;
  width?: number | string;
  config?: Partial<
    Omit<ModalProps, 'open' | 'onOpen' | 'onClose' | 'children'>
  >;
};

export type ModalContextProps = {
  openModal: (config: ModalConfig) => void;
  closeModal: (close: Noop) => void;
};

export const ModalContext = createContext<ModalContextProps>({
  openModal: () => undefined,
  closeModal: () => undefined,
});

export type ModalProviderProps = {
  children: ReactNode;
};

export default function ModalProvider({ children }: ModalProviderProps) {
  const [cfg, setCfg] = useState<ModalConfig>({
    content: <></>,
    onClose: () => {},
    config: {},
  });
  const [open, setOpen] = useState(false);

  const openModal = (config: ModalConfig) => {
    setCfg(() => config);
    setOpen(true);
  };

  const closeModal = (onClose: (close: Noop) => void) => {
    if (onClose) {
      onClose(() => setOpen(false));
      return;
    }

    setOpen(false);

    if (cfg.onClose) {
      cfg.onClose();
    }
  };

  const modalProps = cfg.config ?? {};

  return (
    <ModalContext.Provider value={{ openModal, closeModal }}>
      <Modal keepMounted {...modalProps} open={open} setOpen={setOpen}>
        {cfg.content}
      </Modal>
      {children}
    </ModalContext.Provider>
  );
}
