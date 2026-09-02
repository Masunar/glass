import { Typography } from '@mui/material';

import CategoryIconBadge from './CategoryIconBadge';
import type {
  DataHubCategory,
  DataHubLabels,
  DataHubSlotProps,
} from './types.d';

import { Card } from '@salvon/components/card';
import { Flex } from '@salvon/components/div';
import { Link } from '@salvon/components/navigation';
import { usePalette } from '@salvon/hooks/useTheme';

export type CategoryCardProps = {
  category: DataHubCategory;
  onOpen: (id: string) => void;
  sampleCount?: number;
  labels?: Pick<DataHubLabels, 'count'>;
  slotProps?: Pick<DataHubSlotProps, 'card' | 'title' | 'sample' | 'iconBadge'>;
};

export default function CategoryCard({
  category,
  onOpen,
  sampleCount = 3,
  labels,
  slotProps,
}: CategoryCardProps) {
  const palette = usePalette();
  const accent = category.accentColor ?? '#94A3B8';

  const sample =
    sampleCount > 0
      ? category.items
          .slice(0, sampleCount)
          .map((item) => item.label)
          .join(' · ')
      : '';

  const handleClick =
    category.onClick ?? (category.path ? undefined : () => onOpen(category.id));

  const card = (
    <Card
      onClick={handleClick}
      {...slotProps?.card}
      sx={{
        cursor: 'pointer',
        height: '100%',
        bgcolor: category.bgColor,
        transition: 'border-color 120ms, transform 120ms',
        '&:hover': { borderColor: accent, transform: 'translateY(-2px)' },
        ...((palette.salvon?.data_hub?.card ?? {}) as object),
        ...slotProps?.card?.sx,
      }}
    >
      <Flex column sx={{ gap: 1.5 }}>
        <Flex aCenter sx={{ gap: 1.3 }}>
          {category.icon !== null && (
            <CategoryIconBadge
              icon={category.icon?.element}
              color={category.icon?.color}
              bgColor={category.icon?.bgColor ?? accent}
              size={slotProps?.iconBadge?.size}
              sx={slotProps?.iconBadge?.sx}
            />
          )}
          <Flex column>
            <Typography
              {...slotProps?.title}
              sx={{
                fontWeight: 600,
                fontSize: '0.94rem',
                lineHeight: 1.3,
                ...slotProps?.title?.sx,
              }}
            >
              {category.label}
            </Typography>
            <Typography
              variant="body2"
              color="text.secondary"
              sx={{ fontSize: '0.85rem', lineHeight: 0.92 }}
            >
              {labels?.count?.(category.items.length) ?? category.items.length}
            </Typography>
          </Flex>
        </Flex>
        {sample && (
          <Typography
            variant="body2"
            color="text.secondary"
            {...slotProps?.sample}
            sx={{
              display: '-webkit-box',
              WebkitLineClamp: 2,
              WebkitBoxOrient: 'vertical',
              overflow: 'hidden',
              ...slotProps?.sample?.sx,
            }}
          >
            {sample}
          </Typography>
        )}
      </Flex>
    </Card>
  );

  if (category.path) {
    return (
      <Link href={category.path} sx={{ display: 'block', height: '100%' }}>
        {card}
      </Link>
    );
  }

  return card;
}
