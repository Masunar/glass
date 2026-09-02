import type { CalendarView } from '../types.d';
import { YEARS_PER_PAGE } from '../useCalendarState';
import type { Dayjs } from 'dayjs';
import type { ReactNode } from 'react';
import {
  LuChevronDown,
  LuChevronLeft,
  LuChevronRight,
  LuChevronsLeft,
  LuChevronsRight,
} from 'react-icons/lu';

import { Flex } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';
import { capitalizeFirstChar } from '@salvon/utils/string';

const navBtnSx = {
  width: 30,
  height: 30,
  minWidth: 30,
  '& svg': { fontSize: 16 },
};

type CalendarHeaderProps = {
  view: CalendarView;
  visibleMonth: Dayjs;
  yearRangeStart: number;
  locale: string;
  canOpenMonth: boolean;
  canOpenYear: boolean;
  /** Which side's nav arrows to show. Range panels show only their outer side. */
  arrows?: 'both' | 'left' | 'right';
  onStep: (months: number) => void;
  onStepYears: (years: number) => void;
  onOpenMonthView: () => void;
  onOpenYearView: () => void;
};

export default function CalendarHeader({
  view,
  visibleMonth,
  yearRangeStart,
  locale,
  canOpenMonth,
  canOpenYear,
  arrows = 'both',
  onStep,
  onStepYears,
  onOpenMonthView,
  onOpenYearView,
}: CalendarHeaderProps) {
  const monthName = capitalizeFirstChar(
    visibleMonth.locale(locale).format('MMMM'),
  );
  const year = String(visibleMonth.year());
  const showLeft = arrows !== 'right';
  const showRight = arrows !== 'left';

  if (view === 'year') {
    return (
      <Flex jBetween aCenter gap={1} mb={1}>
        {showLeft ? (
          <IconButton
            sx={navBtnSx}
            onClick={() => onStepYears(-YEARS_PER_PAGE)}
          >
            <LuChevronLeft />
          </IconButton>
        ) : (
          <Spacer />
        )}
        <Flex jCenter fw sx={{ fontWeight: 700, fontSize: '0.9rem' }}>
          {yearRangeStart} – {yearRangeStart + YEARS_PER_PAGE - 1}
        </Flex>
        {showRight ? (
          <IconButton sx={navBtnSx} onClick={() => onStepYears(YEARS_PER_PAGE)}>
            <LuChevronRight />
          </IconButton>
        ) : (
          <Spacer />
        )}
      </Flex>
    );
  }

  if (view === 'month') {
    return (
      <Flex jBetween aCenter gap={1} mb={1}>
        {showLeft ? (
          <IconButton sx={navBtnSx} onClick={() => onStepYears(-1)}>
            <LuChevronLeft />
          </IconButton>
        ) : (
          <Spacer />
        )}
        {canOpenYear ? (
          <PickerLabel label={year} onClick={onOpenYearView} />
        ) : (
          <StaticLabel>{year}</StaticLabel>
        )}
        {showRight ? (
          <IconButton sx={navBtnSx} onClick={() => onStepYears(1)}>
            <LuChevronRight />
          </IconButton>
        ) : (
          <Spacer />
        )}
      </Flex>
    );
  }

  return (
    <Flex aCenter gap={0.5} mb={1}>
      {showLeft && (
        <>
          <IconButton sx={navBtnSx} onClick={() => onStepYears(-1)}>
            <LuChevronsLeft />
          </IconButton>
          <IconButton sx={navBtnSx} onClick={() => onStep(-1)}>
            <LuChevronLeft />
          </IconButton>
        </>
      )}

      {canOpenMonth ? (
        <PickerLabel label={monthName} onClick={onOpenMonthView} grow />
      ) : (
        <StaticLabel grow>{monthName}</StaticLabel>
      )}
      {canOpenYear ? (
        <PickerLabel label={year} onClick={onOpenYearView} />
      ) : (
        <StaticLabel>{year}</StaticLabel>
      )}

      {showRight && (
        <>
          <IconButton sx={navBtnSx} onClick={() => onStep(1)}>
            <LuChevronRight />
          </IconButton>
          <IconButton sx={navBtnSx} onClick={() => onStepYears(1)}>
            <LuChevronsRight />
          </IconButton>
        </>
      )}
    </Flex>
  );
}

function Spacer() {
  return <Flex sx={{ width: navBtnSx.width, flexShrink: 0 }} />;
}

function StaticLabel({
  children,
  grow,
}: {
  children: ReactNode;
  grow?: boolean;
}) {
  return (
    <Flex
      jCenter
      fw={grow}
      sx={{ fontWeight: 700, fontSize: '0.82rem', px: grow ? 0 : 1 }}
    >
      {children}
    </Flex>
  );
}

function PickerLabel({
  label,
  onClick,
  grow,
}: {
  label: string;
  onClick: () => void;
  grow?: boolean;
}) {
  return (
    <Flex
      center
      onClick={onClick}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          onClick();
        }
      }}
      sx={{
        flex: grow ? 1 : undefined,
        gap: 0.25,
        px: 1,
        py: 0.5,
        borderRadius: '8px',
        border: '1px solid',
        borderColor: 'divider',
        cursor: 'pointer',
        fontWeight: 700,
        fontSize: '0.82rem',
        whiteSpace: 'nowrap',
        '&:hover': { backgroundColor: 'action.hover' },
      }}
    >
      {label}
      <LuChevronDown size={14} opacity={0.6} />
    </Flex>
  );
}
