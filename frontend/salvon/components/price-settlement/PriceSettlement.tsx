import {
  LinearProgress,
  Typography,
  type TypographyProps,
} from '@mui/material';

import { type ReactNode } from 'react';
import { MdCheckCircleOutline } from 'react-icons/md';

import { Card, type CardProps } from '@salvon/components/card';
import { Flex, type FlexProps } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';
import type { SlotItem } from '@salvon/types';
import { voc } from '@salvon/utils/object';

export type PriceSettlementRow = {
  label: ReactNode;
  value: ReactNode;
  /** Small chip rendered after the label, e.g. VAT rate `8%`. */
  badge?: ReactNode;
  /** Highlighted row (bold, primary color) — e.g. Brutto total. */
  highlighted?: boolean;
  /** Per-row style overrides, applied after base + theme. */
  slotProps?: {
    label?: SlotItem<TypographyProps>;
    value?: SlotItem<TypographyProps>;
  };
};

export type PriceSettlementLimit = {
  label: ReactNode;
  /** Formatted limit value shown on the right. */
  value: ReactNode;
  /** Progress percentage, 0..100. */
  progress: number;
  /** When true, renders the exceeded style + message instead of the ok one. */
  exceeded?: boolean;
  okMessage?: ReactNode;
  exceededMessage?: ReactNode;
  okIcon?: ReactNode;
  exceededIcon?: ReactNode;
};

export type PriceSettlementProps = {
  title: ReactNode;
  /** Big value in the header (e.g. brutto). */
  value: ReactNode;
  /** Small line under the header value (e.g. netto + VAT breakdown text). */
  subtitle?: ReactNode;
  limit?: PriceSettlementLimit;
  breakdownTitle?: ReactNode;
  rows?: PriceSettlementRow[];
  slotProps?: {
    card?: SlotItem<CardProps>;
    header?: SlotItem<FlexProps>;
    title?: SlotItem<TypographyProps>;
    value?: SlotItem<TypographyProps>;
    subtitle?: SlotItem<TypographyProps>;
    body?: SlotItem<FlexProps>;
  };
} & Omit<CardProps, 'children' | 'heading' | 'title'>;

export default function PriceSettlement({
  title,
  value,
  subtitle,
  limit,
  breakdownTitle,
  rows,
  slotProps,
  sx,
  ...cardProps
}: PriceSettlementProps) {
  const palette = usePalette();
  const t = (palette?.salvon?.price_settlement ?? {}) as NonNullable<
    NonNullable<typeof palette.salvon>['price_settlement']
  >;

  const divider =
    t.divider ?? (palette.mode === 'dark' ? '#303030' : '#dbe1ea');

  return (
    <Card
      fw
      padding={0}
      {...slotProps?.card}
      {...cardProps}
      sx={{
        overflow: 'hidden',
        ...(t.root as any),
        ...slotProps?.card?.sx,
        ...sx,
      }}
    >
      {/* Header */}
      <Flex
        column
        gap={0.5}
        {...slotProps?.header}
        sx={{
          p: 2.5,
          backgroundColor: 'primary.main',
          color: 'primary.contrastText',
          ...(t.header as object),
          ...slotProps?.header?.sx,
        }}
      >
        <Typography
          {...slotProps?.title}
          sx={{
            fontSize: '0.8rem',
            fontWeight: 700,
            letterSpacing: '0.08em',
            textTransform: 'uppercase',
            opacity: 0.85,
            ...(t.headerTitle as object),
            ...slotProps?.title?.sx,
          }}
        >
          {title}
        </Typography>
        <Typography
          {...slotProps?.value}
          sx={{
            fontSize: '2.4rem',
            fontWeight: 800,
            lineHeight: 1.05,
            ...(t.headerValue as object),
            ...slotProps?.value?.sx,
          }}
        >
          {value}
        </Typography>
        {subtitle && (
          <Typography
            {...slotProps?.subtitle}
            sx={{
              fontSize: '0.9rem',
              opacity: 0.85,
              ...(t.headerSubtitle as object),
              ...slotProps?.subtitle?.sx,
            }}
          >
            {subtitle}
          </Typography>
        )}
      </Flex>

      <Flex column {...slotProps?.body}>
        {limit && <LimitSection limit={limit} theme={t} />}

        {(breakdownTitle || rows?.length) && (
          <Flex
            column
            gap={1.5}
            sx={{
              p: 2.5,
              ...voc(!!limit, { borderTop: `1px solid ${divider}` }),
              ...(t.section as object),
            }}
          >
            {breakdownTitle && (
              <Typography
                sx={{
                  fontSize: '0.78rem',
                  fontWeight: 700,
                  letterSpacing: '0.06em',
                  textTransform: 'uppercase',
                  color: 'text.secondary',
                  ...(t.breakdownTitle as object),
                }}
              >
                {breakdownTitle}
              </Typography>
            )}
            {rows?.map((row, i) => (
              <Row key={i} row={row} theme={t} />
            ))}
          </Flex>
        )}
      </Flex>
    </Card>
  );
}

function LimitSection({
  limit,
  theme,
}: {
  limit: PriceSettlementLimit;
  theme: any;
}) {
  const bar = theme.limitBar ?? {};
  const exceeded = !!limit.exceeded;
  const okColor = theme.limitOk ?? 'success.main';
  const exceededColor = theme.limitExceeded ?? 'error.main';

  const icon = exceeded
    ? (limit.exceededIcon ?? <MdCheckCircleOutline />)
    : (limit.okIcon ?? <MdCheckCircleOutline />);
  const message = exceeded ? limit.exceededMessage : limit.okMessage;

  return (
    <Flex column gap={1.25} sx={{ p: 2.5, ...(theme.section as object) }}>
      <Flex jBetween aCenter gap={1}>
        <Typography sx={{ color: 'text.secondary', fontSize: '0.95rem' }}>
          {limit.label}
        </Typography>
        <Typography sx={{ fontWeight: 700, whiteSpace: 'nowrap' }}>
          {limit.value}
        </Typography>
      </Flex>
      <LinearProgress
        variant="determinate"
        value={Math.max(0, Math.min(100, limit.progress))}
        sx={{
          height: 8,
          borderRadius: 999,
          backgroundColor: bar.track ?? 'action.hover',
          '& .MuiLinearProgress-bar': {
            borderRadius: 999,
            backgroundColor: exceeded
              ? (bar.barExceeded ?? exceededColor)
              : (bar.bar ?? okColor),
          },
        }}
      />
      {message && (
        <Flex
          aCenter
          gap={0.75}
          sx={{
            color: exceeded ? exceededColor : okColor,
            fontWeight: 700,
            fontSize: '0.95rem',
            '& svg': { fontSize: '1.15rem' },
          }}
        >
          {icon}
          <span>{message}</span>
        </Flex>
      )}
    </Flex>
  );
}

function Row({ row, theme }: { row: PriceSettlementRow; theme: any }) {
  const highlighted = !!row.highlighted;
  const { label: labelSlot, value: valueSlot } = row.slotProps ?? {};

  return (
    <Flex jBetween aCenter gap={1}>
      <Flex aCenter gap={1}>
        <Typography
          {...labelSlot}
          sx={{
            fontSize: '1rem',
            ...voc(
              highlighted,
              { fontWeight: 700, color: 'text.primary' },
              { color: 'text.secondary' },
            ),
            // theme base, then per-row override
            ...((highlighted
              ? theme.rowLabelHighlighted
              : theme.rowLabel) as object),
            ...labelSlot?.sx,
          }}
        >
          {row.label}
        </Typography>
        {row.badge}
      </Flex>
      <Typography
        {...valueSlot}
        sx={{
          whiteSpace: 'nowrap',
          ...voc(
            highlighted,
            { fontWeight: 800, color: 'primary.main' },
            { fontWeight: 500, color: 'text.primary' },
          ),
          ...((highlighted ? theme.breakdownTotal : theme.rowValue) as object),
          ...valueSlot?.sx,
        }}
      >
        {row.value}
      </Typography>
    </Flex>
  );
}
