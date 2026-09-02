import Kbd from './Kbd';
import type { ReactNode } from 'react';

import { Flex } from '@salvon/components/div';

export type LegendProps = {
  keys: string;
  label: ReactNode;
};

export default function Legend({ keys, label }: LegendProps) {
  if (label == null) return null;

  return (
    <Flex aCenter gap={0.75}>
      <Kbd sx={{ minWidth: 20, textAlign: 'center' }}>{keys}</Kbd>
      <span>{label}</span>
    </Flex>
  );
}
