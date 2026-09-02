import { Typography, alpha, type TypographyProps } from '@mui/material';

import type { SettingsCenterItem as Item } from './types.d';

import { Flex } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';
import type { SlotItem } from '@salvon/types';
import { voc } from '@salvon/utils/object';

export type SettingsCenterItemProps = {
  item: Item;
  active: boolean;
  onSelect: (id: string) => void;
  slotProps?: { label?: SlotItem<TypographyProps> };
};

export default function SettingsCenterItem({
  item,
  active,
  onSelect,
  slotProps,
}: SettingsCenterItemProps) {
  const palette = usePalette();
  const primary = palette.primary?.main ?? '#254a94';
  const isDark = palette.mode === 'dark';
  // Navy primary reads too dark over a black bg — lift the active tint in dark.
  const activeBg = alpha(primary, isDark ? 0.28 : 0.07);
  const activeAccent = isDark
    ? palette.primary?.light ?? '#5b7fd4'
    : primary;

  return (
    <Flex
      aCenter
      role="button"
      tabIndex={item.disabled ? -1 : 0}
      aria-current={active}
      aria-disabled={item.disabled}
      onClick={() => !item.disabled && onSelect(item.id)}
      onKeyDown={(e) => {
        if (item.disabled) return;
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onSelect(item.id);
        }
      }}
      sx={{
        gap: 1.25,
        pl: 2,
        pr: 2,
        py: 0.75,
        borderLeft: '3px solid transparent',
        cursor: item.disabled ? 'not-allowed' : 'pointer',
        color: active ? activeAccent : 'text.secondary',
        transition: 'background-color 120ms, color 120ms',
        ...voc(item.disabled, { opacity: 0.5 }),
        ...voc(!item.disabled && !active, {
          '&:hover': { backgroundColor: 'action.hover', color: 'text.primary' },
        }),
        ...voc(active, {
          backgroundColor: activeBg,
          borderLeftColor: primary,
        }),
        ...((palette.salvon?.settings_center?.sidebarItem ?? {}) as object),
        ...voc(
          active,
          (palette.salvon?.settings_center?.sidebarItemActive ?? {}) as object,
        ),
      }}
    >
      {item.icon && (
        <Flex
          center
          sx={{
            width: 28,
            height: 28,
            flexShrink: 0,
            borderRadius: '8px',
            fontSize: 15,
            color: active ? '#fff' : 'text.disabled',
            backgroundColor: active
              ? primary
              : isDark
                ? 'rgba(255,255,255,0.08)'
                : 'action.hover',
            transition: 'background-color 120ms, color 120ms',
            ...((palette.salvon?.settings_center?.toggleIcon ?? {}) as object),
            ...voc(
              active,
              (palette.salvon?.settings_center?.toggleIconActive ??
                {}) as object,
            ),
          }}
        >
          {item.icon}
        </Flex>
      )}
      <Typography
        {...slotProps?.label}
        sx={{
          flex: 1,
          minWidth: 0,
          overflow: 'hidden',
          textOverflow: 'ellipsis',
          whiteSpace: 'nowrap',
          fontSize: '0.9rem',
          fontWeight: active ? 600 : 500,
          color: 'inherit',
          ...slotProps?.label?.sx,
        }}
      >
        {item.label}
      </Typography>
      {item.count != null && (
        <Typography
          component="span"
          sx={{
            flexShrink: 0,
            px: 0.75,
            py: 0.25,
            borderRadius: '6px',
            fontSize: '0.75rem',
            fontWeight: 600,
            lineHeight: 1.4,
            fontVariantNumeric: 'tabular-nums',
            color: active ? '#fff' : 'text.secondary',
            backgroundColor: active
              ? primary
              : isDark
                ? 'rgba(255,255,255,0.08)'
                : alpha(primary, 0.06),
            ...((palette.salvon?.settings_center?.countBadge ?? {}) as object),
            ...voc(
              active,
              (palette.salvon?.settings_center?.countBadgeActive ??
                {}) as object,
            ),
          }}
        >
          {item.count}
        </Typography>
      )}
    </Flex>
  );
}
