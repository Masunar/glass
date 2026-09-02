import { CircularProgress } from '@mui/material';

import { PiCheck, PiX } from 'react-icons/pi';

import { Flex } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';
import { useTranslation } from '@salvon/hooks/useTranslation';

type Props = {
  save: () => void;
  cancel: () => void;
  loading: boolean;
};

export default function EditControls({ save, cancel, loading }: Props) {
  const t = useTranslation();

  if (loading) {
    return (
      <Flex aCenter sx={{ px: 0.5 }}>
        <CircularProgress size={16} />
      </Flex>
    );
  }

  return (
    <Flex aCenter>
      <IconButton
        variant="mui"
        size="small"
        icon={<PiX />}
        color="error"
        sx={{ p: 0.25 }}
        label={t('cancel')}
        onMouseDown={(event) => event.preventDefault()}
        onClick={(event) => {
          event.stopPropagation();
          cancel();
        }}
      />
      <IconButton
        variant="mui"
        size="small"
        icon={<PiCheck />}
        color="success"
        sx={{ p: 0.25 }}
        label={t('save')}
        onMouseDown={(event) => event.preventDefault()}
        onClick={(event) => {
          event.stopPropagation();
          save();
        }}
      />
    </Flex>
  );
}
