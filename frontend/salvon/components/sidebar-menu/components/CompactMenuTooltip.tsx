import { Tooltip } from '@mui/material';

import { type ReactElement, type ReactNode } from 'react';

interface Props {
  compactMode: boolean;
  isNested: boolean;
  title: ReactNode;
  children: ReactElement;
}

export default function CompactMenuTooltip({
  compactMode,
  isNested,
  title,
  children,
}: Props) {
  if (!compactMode || isNested) {
    return children;
  }

  return (
    <Tooltip title={title} placement="right">
      {children}
    </Tooltip>
  );
}
