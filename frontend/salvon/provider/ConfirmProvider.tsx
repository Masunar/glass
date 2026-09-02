import { type ReactNode, useState } from 'react';
import { createContext } from 'react';

import { ConfirmModal } from '@salvon/components/modal';
import { useTranslation } from '@salvon/hooks/useTranslation';
import type { Noop } from '@salvon/types';

export type ConfirmHandleFunction = ({
  closeModal,
  setLoading,
}: {
  closeModal: Noop;
  setLoading: (v: boolean) => void;
}) => void;

export type ConfirmProps = {
  title: ReactNode;
  onConfirm: ConfirmHandleFunction;
  onCancel?: ConfirmHandleFunction;
  confirmLabel?: string;
  cancelLabel?: string;
  autoCloseOnConfirm?: boolean;
  autoCloseOnCancel?: boolean;
};

export type ConfirmContextProps = {
  confirm: (props: ConfirmProps) => void;
};

export const ConfirmContext = createContext<ConfirmContextProps>({
  confirm: () => undefined,
});

export type ConfirmProviderProps = {
  children: ReactNode;
};

export default function ConfirmProvider({ children }: ConfirmProviderProps) {
  const t = useTranslation();
  const [loading, setLoading] = useState(false);
  const [cfg, setCfg] = useState<ConfirmProps>({
    title: '',
    onConfirm: ({ closeModal }) => {
      closeModal();
    },
  });
  const [open, setOpen] = useState(false);

  const confirm = (config: ConfirmProps) => {
    setCfg(() => config);
    setOpen(true);
  };

  return (
    <ConfirmContext.Provider value={{ confirm }}>
      <ConfirmModal
        loading={loading}
        onConfirm={({ closeModal }) =>
          cfg.onConfirm({ closeModal, setLoading })
        }
        onCancel={({ closeModal }) => {
          if (cfg.onCancel) {
            cfg?.onCancel({ closeModal, setLoading });
          }
        }}
        autoCloseOnConfirm={cfg.autoCloseOnConfirm ?? true}
        autoCloseOnCancel={cfg.autoCloseOnCancel ?? true}
        open={open}
        setOpen={setOpen}
        confirmLabel={cfg.confirmLabel ?? t('yes')}
        cancelLabel={cfg.cancelLabel ?? t('no')}
      >
        {cfg.title}
      </ConfirmModal>
      {children}
    </ConfirmContext.Provider>
  );
}
