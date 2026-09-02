import { Chip, Typography } from '@mui/material';

import Default from './Default';
import Hook from './Hook';
import { MdListAlt } from 'react-icons/md';

import { Card } from '@salvon/components/card';
import { Div, Flex } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';

type Props = {};
export default function Form({}: Props) {
  const palette = usePalette();

  return (
    <Card
      fw
      heading={{
        icon: <MdListAlt />,
        title: 'Form',
        subtitle:
          'Pełny zestaw pól — domyślny oraz sterowany zewnętrznym hookiem (disabled)',
      }}
      slotProps={{
        body: {
          sx: {
            paddingTop: 0,
          },
        },
      }}
    >
      <Flex fw>
        <Div
          fw
          pr={3}
          sx={{
            pt: 2,
          }}
        >
          <ColumnHeader title="Default form" status="interactive" />
          <Default />
        </Div>
        <Div
          fw
          pl={3}
          sx={{
            pt: 2,
            borderLeft: `1px solid ${palette.mode === 'dark' ? '#303030' : '#dbe1ea'}`,
          }}
        >
          <ColumnHeader title="Controlled by external hook" status="disabled" />
          <Hook />
        </Div>
      </Flex>
    </Card>
  );
}

type ColumnHeaderProps = {
  title: string;
  status: 'interactive' | 'disabled';
};

const ColumnHeader = ({ title, status }: ColumnHeaderProps) => {
  const interactive = status === 'interactive';

  return (
    <Flex aCenter gap={1} mb={2}>
      <Typography sx={{ fontWeight: 700 }}>{title}</Typography>
      <Chip
        label={interactive ? 'Interaktywny' : 'Disabled'}
        size="small"
        sx={(theme) => ({
          fontSize: 10,
          fontWeight: 700,
          letterSpacing: '0.06em',
          textTransform: 'uppercase',
          height: 20,
          color: interactive
            ? theme.palette.success.main
            : theme.palette.text.secondary,
          backgroundColor: interactive
            ? theme.palette.mode === 'dark'
              ? 'rgba(76,175,80,0.16)'
              : 'rgba(76,175,80,0.12)'
            : theme.palette.action.hover,
        })}
      />
    </Flex>
  );
};
