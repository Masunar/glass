import { Avatar, Typography } from '@mui/material';

import type { ReactNode } from 'react';

import { Flex } from '@salvon/components/div';

type Props = {
  icon: ReactNode;
  label: string;
  color: string;
  onClick: () => void;
};

export default function ActionButton({ icon, label, color, onClick }: Props) {
  return (
    <Flex
    column
    center
    gap={0.5}
    sx={{ cursor: 'pointer', flex: 1 }}
    onClick={onClick}
  >
    <Avatar
      sx={{
        width: 52,
        height: 52,
        background: color,
        color: '#fff',
        fontSize: 22,
        borderRadius: 3,
      }}
      variant="rounded"
    >
      {icon}
    </Avatar>
    <Typography
      variant="caption"
      sx={{
        fontWeight: 600,
        letterSpacing: 0.5,
        textTransform: 'uppercase',
        textAlign: 'center',
      }}
    >
      {label}
    </Typography>
  </Flex>
  );
}
