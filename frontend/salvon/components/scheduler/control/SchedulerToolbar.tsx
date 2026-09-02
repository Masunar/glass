import { Tab, Tabs, Typography } from '@mui/material';

import { type SchedulerView } from '../types.d';
import { type ReactNode } from 'react';
import { MdChevronLeft, MdChevronRight } from 'react-icons/md';

import { Button } from '@salvon/components/button';
import { Div, Flex } from '@salvon/components/div';
import { type FlexProps } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { type SlotItem } from '@salvon/types';

export type SchedulerToolbarProps = {
  view: SchedulerView;
  views: SchedulerView[];
  viewLabels?: Partial<Record<SchedulerView, ReactNode>>;
  label: string;
  onView: (view: SchedulerView) => void;
  onNavigate: (action: 'PREV' | 'NEXT' | 'TODAY') => void;
  slotProps?: SlotItem<FlexProps>;
};

const H = 38;

export default function SchedulerToolbar({
  view,
  views,
  viewLabels,
  label,
  onView,
  onNavigate,
  slotProps,
}: SchedulerToolbarProps) {
  const t = useTranslation();

  const labels: Record<SchedulerView, ReactNode> = {
    year: t('scheduler_view_year', { defaultValue: 'Rok' }),
    month: t('scheduler_view_month', { defaultValue: 'Miesiąc' }),
    week: t('scheduler_view_week', { defaultValue: 'Tydzień' }),
    day: t('scheduler_view_day', { defaultValue: 'Dzień' }),
    list: t('scheduler_view_list', { defaultValue: 'Agenda' }),
    day_list: t('scheduler_view_day_list', { defaultValue: 'Agenda dnia' }),
    ...viewLabels,
  };

  const { sx, ...rootProps } = slotProps ?? {};

  return (
    <Div
      {...rootProps}
      sx={{
        display: 'grid',
        gridTemplateColumns: '1fr auto 1fr',
        alignItems: 'center',
        gap: 1,
        mb: 2,
        ...sx,
      }}
    >
      <Flex aCenter sx={{ gap: 1 }}>
        <Button
          variant="contained"
          onClick={() => onNavigate('TODAY')}
          sx={{ height: H, px: 2, minWidth: 0 }}
        >
          {t('scheduler_today', { defaultValue: 'Dziś' })}
        </Button>
        <IconButton
          onClick={() => onNavigate('PREV')}
          sx={{ width: H, height: H }}
        >
          <MdChevronLeft />
        </IconButton>
        <IconButton
          onClick={() => onNavigate('NEXT')}
          sx={{ width: H, height: H }}
        >
          <MdChevronRight />
        </IconButton>
      </Flex>

      <Typography
        sx={{
          fontWeight: 700,
          textAlign: 'center',
          textTransform: 'capitalize',
        }}
      >
        {label}
      </Typography>

      <Flex jEnd>
        {views.length > 1 && (
          <Tabs value={view} onChange={(_, v) => onView(v)}>
            {views.map((v) => {
              return <Tab key={v} value={v} label={labels[v]} />;
            })}
          </Tabs>
        )}
      </Flex>
    </Div>
  );
}
