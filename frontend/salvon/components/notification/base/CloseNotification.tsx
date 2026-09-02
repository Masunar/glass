import { CircularProgress, Typography } from '@mui/material';

import { PiXBold } from 'react-icons/pi';

import { Flex } from '@salvon/components/div';
import { useTimeoutProgress } from '@salvon/hooks/useTimeoutProgress';

export type CloseNotificationProps = {
  onClose: () => void;
  autoclose?: number;
  color?: string;
  size?: number;
};

export default function CloseNotification({
  onClose,
  autoclose,
  color,
  size = 26,
}: CloseNotificationProps) {
  const { progress } = useTimeoutProgress(autoclose, { onComplete: onClose });

  return (
    <Flex
      center
      onClick={onClose}
      sx={{
        position: 'relative',
        flex: '0 0 auto',
        width: size,
        height: size,
        borderRadius: '50%',
        cursor: 'pointer',
      }}
    >
      {typeof autoclose === 'number' && (
        <>
          {/* Track */}
          <CircularProgress
            variant="determinate"
            value={100}
            size={size}
            thickness={3}
            sx={{
              color: 'action.disabledBackground',
              position: 'absolute',
              top: 0,
              left: 0,
              pointerEvents: 'none',
            }}
          />
          {/* Countdown */}
          <CircularProgress
            variant="determinate"
            value={progress}
            size={size}
            thickness={3}
            sx={{
              color,
              position: 'absolute',
              top: 0,
              left: 0,
              pointerEvents: 'none',
              '& .MuiCircularProgress-circle': { transition: 'none' },
            }}
          />
        </>
      )}
      <Typography>
        <PiXBold style={{ fontSize: '0.65rem' }} />
      </Typography>
    </Flex>
  );
}
