import { Divider } from '@mui/material';

import GroupLabel from './GroupLabel';
import dayjs, { type Dayjs } from 'dayjs';
import { useState } from 'react';
import { LuCalendarClock } from 'react-icons/lu';

import { Card } from '@salvon/components/card';
import { Div, Flex } from '@salvon/components/div';
import {
  PickerCalendar,
  PickerInput,
  RangePickerCalendar,
  RangePickerInput,
} from '@salvon/components/picker-calendar';
import type {
  DateRange,
  PickerCalendarProps,
  QuickAction,
  RangePreset,
} from '@salvon/components/picker-calendar';

const rangePresets: RangePreset[] = [
  {
    label: 'Ten tydzień',
    value: () => ({
      start: dayjs().startOf('week'),
      end: dayjs().endOf('week'),
    }),
  },
  {
    label: 'Ten miesiąc',
    value: () => ({
      start: dayjs().startOf('month'),
      end: dayjs().endOf('month'),
    }),
  },
  {
    label: 'Do końca roku',
    value: () => ({ start: dayjs(), end: dayjs().endOf('year') }),
  },
  {
    label: 'Kolejne 12 mies.',
    value: () => ({ start: dayjs(), end: dayjs().add(12, 'month') }),
  },
];

const timeRangePresets: RangePreset[] = [
  {
    label: 'Poranek',
    value: () => ({
      start: dayjs().hour(6).minute(0).second(0),
      end: dayjs().hour(12).minute(0).second(0),
    }),
  },
  {
    label: 'Godziny pracy',
    value: () => ({
      start: dayjs().hour(9).minute(0).second(0),
      end: dayjs().hour(17).minute(0).second(0),
    }),
  },
  {
    label: 'Cały dzień',
    value: () => ({
      start: dayjs().startOf('day'),
      end: dayjs().endOf('day'),
    }),
  },
];

const quickActions: QuickAction[] = [
  { label: 'Dziś', value: () => dayjs() },
  { label: 'Jutro', value: () => dayjs().add(1, 'day') },
  { label: 'Za tydzień', value: () => dayjs().add(1, 'week') },
];

type Variant = {
  id: string;
  label: string;
  props: Partial<PickerCalendarProps>;
};

const datetimeVariants: Variant[] = [
  {
    id: 'datetime',
    label: 'Datetime · 24h',
    props: { mode: 'datetime', quickActions },
  },
  {
    id: 'datetime-12',
    label: 'Datetime · 12h (AM/PM)',
    props: { mode: 'datetime', hourCycle: 12, quickActions },
  },
  {
    id: 'datetime-hm',
    label: 'Datetime · hours + minutes',
    props: {
      mode: 'datetime',
      views: ['year', 'month', 'day', 'hours', 'minutes'],
      quickActions,
    },
  },
];

const dateVariants: Variant[] = [
  { id: 'date', label: 'Date', props: { mode: 'date', quickActions } },
  {
    id: 'month-year',
    label: 'Month + year',
    props: { mode: 'date', views: ['year', 'month'] },
  },
  { id: 'year', label: 'Year only', props: { mode: 'date', views: ['year'] } },
];

const timeVariants: Variant[] = [
  { id: 'time-24', label: 'Time · 24h', props: { mode: 'time' } },
  {
    id: 'time-12',
    label: 'Time · 12h (AM/PM)',
    props: { mode: 'time', hourCycle: 12 },
  },
  {
    id: 'hm',
    label: 'Hours + minutes',
    props: { mode: 'time', views: ['hours', 'minutes'] },
  },
  {
    id: 'hm-12',
    label: 'Hours + minutes · 12h',
    props: { mode: 'time', views: ['hours', 'minutes'], hourCycle: 12 },
  },
  {
    id: 'ms',
    label: 'Minutes + seconds',
    props: { mode: 'time', views: ['minutes', 'seconds'] },
  },
  {
    id: 'hours',
    label: 'Hours only',
    props: { mode: 'time', views: ['hours'] },
  },
  {
    id: 'hours-12',
    label: 'Hours only · 12h',
    props: { mode: 'time', views: ['hours'], hourCycle: 12 },
  },
  {
    id: 'minutes',
    label: 'Minutes only',
    props: { mode: 'time', views: ['minutes'] },
  },
  {
    id: 'seconds',
    label: 'Seconds only',
    props: { mode: 'time', views: ['seconds'] },
  },
];

export default function MiniCalendar() {
  const [values, setValues] = useState<Record<string, Dayjs | null>>(() =>
    Object.fromEntries(
      [...datetimeVariants, ...dateVariants, ...timeVariants].map((v) => [
        v.id,
        dayjs('2026-04-16 13:07:30'),
      ]),
    ),
  );

  const [range, setRange] = useState<DateRange>({
    start: dayjs('2026-04-10'),
    end: dayjs('2026-04-20'),
  });

  const [timeRange, setTimeRange] = useState<DateRange>({
    start: dayjs('2026-04-16 09:00'),
    end: dayjs('2026-04-16 17:30'),
  });

  const renderVariant = (v: Variant) => (
    <Flex key={v.id} column gap={1}>
      <GroupLabel>{v.label}</GroupLabel>
      <PickerCalendar
        {...v.props}
        value={values[v.id]}
        onChange={(next) => setValues((s) => ({ ...s, [v.id]: next }))}
      />
    </Flex>
  );

  return (
    <Card
      fw
      heading={{
        icon: <LuCalendarClock />,
        title: 'Picker calendar',
        subtitle: 'Date picker i time picker — wszystkie warianty widoków',
      }}
    >
      <Flex column gap={2.5}>
        <GroupLabel>Datetime picker</GroupLabel>
        <Flex gap={3} wrap aStretch>
          {datetimeVariants.map(renderVariant)}
        </Flex>

        <Divider />

        <GroupLabel>Date picker</GroupLabel>
        <Flex gap={3} wrap aStretch>
          {dateVariants.map(renderVariant)}
        </Flex>

        <Divider />

        <GroupLabel>Time picker</GroupLabel>
        <Flex gap={3} wrap aStretch>
          {timeVariants.map(renderVariant)}
        </Flex>

        <Divider />

        <GroupLabel>Range picker</GroupLabel>
        <Flex gap={3} wrap aStretch>
          <Flex column gap={1}>
            <GroupLabel>Date range</GroupLabel>
            <RangePickerCalendar
              value={range}
              onChange={setRange}
              presets={rangePresets}
            />
          </Flex>
          <Flex column gap={1}>
            <GroupLabel>Datetime range · hh:mm:ss</GroupLabel>
            <RangePickerCalendar
              views={['year', 'month', 'day', 'hours', 'minutes', 'seconds']}
              format="D MMM YYYY"
              value={range}
              onChange={setRange}
              presets={rangePresets}
            />
          </Flex>
          <Flex column gap={1}>
            <GroupLabel>Single month</GroupLabel>
            <RangePickerCalendar
              singleMonth
              value={range}
              onChange={setRange}
              presets={rangePresets}
            />
          </Flex>
        </Flex>

        <Divider />

        <GroupLabel>Time range picker</GroupLabel>
        <Flex gap={3} wrap aStretch>
          <Flex column gap={1}>
            <GroupLabel>Hours + minutes · 24h</GroupLabel>
            <RangePickerCalendar
              views={['hours', 'minutes']}
              value={timeRange}
              onChange={setTimeRange}
              presets={timeRangePresets}
            />
          </Flex>
          <Flex column gap={1}>
            <GroupLabel>With seconds · 12h</GroupLabel>
            <RangePickerCalendar
              views={['hours', 'minutes', 'seconds']}
              hourCycle={12}
              value={timeRange}
              onChange={setTimeRange}
            />
          </Flex>
        </Flex>

        <Divider />

        <GroupLabel>Input pickers</GroupLabel>
        <Flex gap={3} wrap aStretch>
          <Flex column gap={1}>
            <GroupLabel>Date input</GroupLabel>
            <Div sx={{ width: 240 }}>
              <PickerInput
                mode="date"
                quickActions={quickActions}
                value={values['date']}
                onChange={(next) => setValues((s) => ({ ...s, date: next }))}
              />
            </Div>
          </Flex>
          <Flex column gap={1}>
            <GroupLabel>Datetime input</GroupLabel>
            <Div sx={{ width: 240 }}>
              <PickerInput
                mode="datetime"
                displayFormat="DD.MM.YYYY HH:mm:ss"
                views={['day', 'month', 'year', 'hours', 'minutes', 'seconds']}
                quickActions={quickActions}
                value={values['datetime']}
                onChange={(next) =>
                  setValues((s) => ({ ...s, datetime: next }))
                }
              />
            </Div>
          </Flex>
          <Flex column gap={1}>
            <GroupLabel>Range input</GroupLabel>
            <Div sx={{ width: 260 }}>
              <RangePickerInput
                presets={rangePresets}
                value={range}
                onChange={setRange}
              />
            </Div>
          </Flex>
          <Flex column gap={1}>
            <GroupLabel>Datetime range input</GroupLabel>
            <Div sx={{ width: 320 }}>
              <RangePickerInput
                views={['day', 'month', 'year', 'hours', 'minutes']}
                displayFormat="DD.MM.YYYY HH:mm"
                presets={rangePresets}
                value={range}
                onChange={setRange}
              />
            </Div>
          </Flex>
          <Flex column gap={1}>
            <GroupLabel>Time range input</GroupLabel>
            <Div sx={{ width: 220 }}>
              <RangePickerInput
                views={['hours', 'minutes']}
                displayFormat="HH:mm"
                presets={timeRangePresets}
                value={timeRange}
                onChange={setTimeRange}
              />
            </Div>
          </Flex>
        </Flex>
      </Flex>
    </Card>
  );
}
