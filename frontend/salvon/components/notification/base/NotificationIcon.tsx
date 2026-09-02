import type { ReactNode } from 'react';

import { Flex } from '@salvon/components/div';

export type NotificationIconProps = {
  icon: ReactNode;
  color?: string;
  bg?: string;
  size?: number;
};

export default function NotificationIcon({
  icon,
  color,
  bg,
  size = 34,
}: NotificationIconProps) {
  return (
    <Flex
      center
      sx={{
        flex: '0 0 auto',
        width: size,
        height: size,
        borderRadius: '8px',
        backgroundColor: bg,
        color,
        fontSize: '1rem',
      }}
    >
      {icon}
    </Flex>
  );
}
