import { Skeleton } from '@mui/material';

import { type CSSProperties } from 'react';

import { Div, Flex } from '@salvon/components/div';

export type SchedulerSkeletonProps = {
  height?: CSSProperties['height'];
};

export default function SchedulerSkeleton({
  height = 640,
}: SchedulerSkeletonProps) {
  return (
    <Div fw sx={{ height }}>
      <Flex aCenter sx={{ gap: 1, mb: 2 }}>
        <Skeleton variant="rounded" width={72} height={34} />
        <Skeleton variant="rounded" width={34} height={34} />
        <Skeleton variant="rounded" width={34} height={34} />
        <Skeleton variant="text" width={160} sx={{ mx: 'auto' }} />
        <Skeleton variant="rounded" width={220} height={34} />
      </Flex>
      <Skeleton
        variant="rounded"
        width="100%"
        height="calc(100% - 50px)"
        sx={{ borderRadius: 2 }}
      />
    </Div>
  );
}
