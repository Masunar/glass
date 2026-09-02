import { Typography } from '@mui/material';

import type { DataHubCategory, DataHubItem, DataHubSlotProps } from './types.d';
import type { ReactNode } from 'react';
import { GrBook } from 'react-icons/gr';
import { MdChevronRight } from 'react-icons/md';

import { AccentIcon } from '@salvon/components/accent';
import { Card } from '@salvon/components/card';
import { Div, Flex } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';
import { Link } from '@salvon/components/navigation';
import { usePalette } from '@salvon/hooks/useTheme';

export type ItemRowProps = {
  item: DataHubItem;
  chevron?: ReactNode | null;
  categoryIcon?: ReactNode;
  slotProps?: Pick<DataHubSlotProps, 'row' | 'rowLink' | 'rowLabel'>;
  category?: DataHubCategory;
};

export default function ItemRow({
  item,
  categoryIcon,
  chevron,
  slotProps,
  category,
}: ItemRowProps) {
  const palette = usePalette();
  const trailing = chevron === null ? null : (chevron ?? <MdChevronRight />);

  const card = (
    <Card
      padding={1.5}
      onClick={item.disabled ? undefined : item.onClick}
      {...slotProps?.row}
      sx={{
        cursor: item.disabled ? 'default' : 'pointer',
        opacity: item.disabled ? 0.5 : 1,
        transition: 'border-color 120ms',
        '&:hover': { borderColor: 'primary.main' },
        ...((palette.salvon?.data_hub?.row ?? {}) as object),
        ...slotProps?.row?.sx,
      }}
    >
      <Flex aCenter jBetween sx={{ gap: 1 }}>
        <Flex aCenter sx={{ gap: 1.5 }}>
          <AccentIcon
            sx={{
              color: category?.icon?.color ?? '#fff',
              background: category?.icon?.bgColor ?? category?.accentColor,
            }}
          >
            {item.icon ?? categoryIcon ?? <GrBook />}
          </AccentIcon>
          <Typography
            color="text.primary"
            {...slotProps?.rowLabel}
            sx={{ fontSize: '0.93rem' }}
          >
            {item.label}
          </Typography>
        </Flex>
        {trailing}
      </Flex>
    </Card>
  );

  if (item.path && !item.disabled) {
    return (
      <Link
        href={item.path}
        {...slotProps?.rowLink}
        sx={{ display: 'block', ...slotProps?.rowLink?.sx }}
      >
        {card}
      </Link>
    );
  }

  return card;
}
