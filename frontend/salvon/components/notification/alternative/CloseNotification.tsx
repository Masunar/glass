import { Typography } from '@mui/material';

import { PiXBold } from 'react-icons/pi';

import { Flex } from '@salvon/components/div';

export type CloseNotificationProps = {
  onClose: () => void;
  size?: number;
};

export default function CloseNotification({
  onClose,
  size = 24,
}: CloseNotificationProps) {
  return (
    <Flex
      center
      onClick={onClose}
      sx={{
        flex: '0 0 auto',
        width: size,
        height: size,
        borderRadius: '6px',
        cursor: 'pointer',
        color: 'text.secondary',
        '&:hover': { backgroundColor: 'action.hover' },
      }}
    >
      <Typography>
        <PiXBold style={{ fontSize: '0.7rem', display: 'block' }} />
      </Typography>
    </Flex>
  );
}
