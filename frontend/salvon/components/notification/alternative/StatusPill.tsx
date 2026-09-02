import { type ReactNode } from 'react';

import { Flex } from '@salvon/components/div';

export type StatusPillProps = {
  children: ReactNode;
  color?: string;
};

export default function StatusPill({ children, color }: StatusPillProps) {
  return (
    <Flex
      aCenter
      gap={0.75}
      sx={{
        flex: '0 0 auto',
        borderRadius: '8px',
        backgroundColor: color ?? 'primary.main',
        color: '#fff',
        padding: '4px 12px',
        fontSize: '0.68rem',
        fontWeight: 700,
        lineHeight: 1,
      }}
    >
      <Flex
        center
        sx={{
          flex: '0 0 auto',
          width: 6,
          height: 6,
          borderRadius: '50%',
          backgroundColor: 'rgba(255,255,255,0.85)',
        }}
      />
      {children}
    </Flex>
  );
}
