import type { ReactNode } from 'react';

import { AccentIcon } from '@salvon/components/accent';

type Props = {
  icon: ReactNode;
};

export default function AltAccentIcon({ icon }: Props) {
  return (
    <AccentIcon
      sx={{
        width: 56,
        height: 56,
        borderRadius: '14px',
        background: '#eef2ff',
        color: '#254a94',
      }}
    >
      {icon}
    </AccentIcon>
  );
}
