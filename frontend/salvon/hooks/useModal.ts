import { use } from 'react';

import { ModalContext } from '@salvon/provider/ModalProvider';

const useModalContext = () => use(ModalContext);

export const useModal = () => {
  const { openModal, closeModal } = useModalContext();

  return [openModal, closeModal] as const;
};
