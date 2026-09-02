import type { ReactNode } from 'react';
import { GrBook } from 'react-icons/gr';

import { Flex, type FlexProps } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';

export type CategoryIconBadgeProps = {
  icon?: ReactNode;
  color?: string;
  bgColor?: string;
  size?: number;
  sx?: FlexProps['sx'];
};

export default function CategoryIconBadge({
  icon,
  color = '#ffffff',
  bgColor = '#94A3B8',
  size = 36,
  sx,
}: CategoryIconBadgeProps) {
  const palette = usePalette();

  return (
    <Flex
      center
      sx={{
        width: size,
        height: size,
        borderRadius: 1.5,
        flexShrink: 0,
        color,
        bgcolor: bgColor,
        fontSize: size * 0.5,
        ...((palette.salvon?.data_hub?.icon_badge ?? {}) as object),
        ...sx,
      }}
    >
      {icon ?? <GrBook />}
    </Flex>
  );
}
