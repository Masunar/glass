import {
  CircularProgress,
  type CircularProgressProps,
  LinearProgress,
  type SxProps,
} from '@mui/material';
import type { LinearProgressProps } from '@mui/material';

import { Flex } from '@salvon/components/div';

interface Props {
  type?: 'circular' | 'linear';
  sx?: SxProps;
  progressProps?: Partial<CircularProgressProps> & Partial<LinearProgressProps>;
}

export default function Loading({
  type = 'circular',
  sx,
  progressProps,
}: Props) {
  return (
    <Flex center fw sx={sx}>
      {type === 'circular' && <CircularProgress {...progressProps} />}
      {type === 'linear' && <LinearProgress {...progressProps} />}
    </Flex>
  );
}
