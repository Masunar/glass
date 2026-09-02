import { Typography } from '@mui/material';

import { type ReactNode } from 'react';

import { Flex } from '@salvon/components/div';
import { type FlexProps } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';
import { type SlotItem } from '@salvon/types';

export type SchedulerHeaderProps = {
  icon?: ReactNode;
  title?: ReactNode;
  subtitle?: ReactNode;
  slotProps?: SlotItem<FlexProps>;
};

export default function SchedulerHeader({
  icon,
  title,
  subtitle,
  slotProps,
}: SchedulerHeaderProps) {
  const palette = usePalette();
  const { sx, ...rootProps } = slotProps ?? {};

  return (
    <Flex aCenter {...rootProps} sx={{ gap: 1.5, mb: 2, ...sx }}>
      {icon && (
        <Flex
          center
          sx={{
            width: 40,
            height: 40,
            flexShrink: 0,
            borderRadius: 2,
            fontSize: 22,
            ...((palette?.salvon?.accent_icon ?? {}) as object),
          }}
        >
          {icon}
        </Flex>
      )}
      <Flex column sx={{ gap: 0.25, minWidth: 0 }}>
        {title && (
          <Typography sx={{ fontWeight: 700, fontSize: 18, lineHeight: 1.2 }}>
            {title}
          </Typography>
        )}
        {subtitle && (
          <Typography sx={{ fontSize: 13, color: 'text.secondary' }}>
            {subtitle}
          </Typography>
        )}
      </Flex>
    </Flex>
  );
}
