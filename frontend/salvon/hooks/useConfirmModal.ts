import { use } from 'react';

import { ConfirmContext } from '@salvon/provider/ConfirmProvider';

export const useConfirmModal = () => use(ConfirmContext).confirm;
