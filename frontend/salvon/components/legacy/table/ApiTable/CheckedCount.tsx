import { Chip } from '@mui/material';

import { useTableSelectionManipulator } from '../hooks/useTableSelectionManipulator';
import { MdClose } from 'react-icons/md';

import { Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function CheckedCount() {
  const t = useTranslation();
  const { totalSelected, clearSelected } = useTableSelectionManipulator();

  if (!totalSelected) {
    return <></>;
  }

  const label = (
    <Flex sx={{ fontWeight: 400, fontSize: 15 }}>
      <MdClose
        onClick={clearSelected}
        style={{ marginRight: 6, fontSize: 20, cursor: 'pointer' }}
      />
      {t('checked')} : {totalSelected}
    </Flex>
  );

  return <Chip sx={{ padding: '14px 10px' }} label={label} />;
}
