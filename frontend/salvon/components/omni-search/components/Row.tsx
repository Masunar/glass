import type { OmniSearchItemType } from '../types.d';
import Item from './Item';
import Kbd from './Kbd';
import { type ForwardedRef, createElement, isValidElement } from 'react';
import { GoArrowUpRight } from 'react-icons/go';

import { Div, Flex } from '@salvon/components/div';
import { useIsDarkMode, usePalette } from '@salvon/hooks/useTheme';

export type RowProps = {
  item: OmniSearchItemType;
  active: boolean;
  onClick: () => void;
  onMouseMove: () => void;
  ref?: ForwardedRef<HTMLDivElement>;
};

export default function Row({
  item,
  active,
  onClick,
  onMouseMove,
  ref,
}: RowProps) {
  const palette = usePalette();
  const dark = useIsDarkMode();
  const cfg = palette.salvon?.omni_search ?? {};
  const text = palette.text?.primary ?? (dark ? '#e5e7eb' : '#1f2937');
  const muted = palette.text?.secondary ?? (dark ? '#8a8f98' : '#94a3b8');
  const activeBg = cfg.activeBg ?? (dark ? 'rgba(59,130,246,0.16)' : '#eef4ff');
  const activeBorder =
    cfg.activeBorder ?? (dark ? 'rgba(59,130,246,0.45)' : '#c7dbff');
  const chipBg = cfg.chipBg ?? (dark ? '#1c1c1f' : '#f4f6f9');
  const chipBorder = cfg.chipBorder ?? (dark ? '#2c2c31' : '#e6e9ef');

  return (
    <Item
      {...(item.boxProps ?? {})}
      ref={ref}
      active={active}
      activeBg={activeBg}
      activeBorder={activeBorder}
      onClick={onClick}
      onMouseMove={onMouseMove}
      sx={{
        height: 44,
        px: 1.25,
        borderRadius: '10px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: 1.5,
        ...(item.boxProps?.sx ?? {}),
      }}
    >
      {item.render ? (
        createElement(item.render)
      ) : (
        <>
          <Flex aCenter gap={1.5} sx={{ minWidth: 0 }}>
            <Flex
              center
              sx={{
                width: 30,
                height: 30,
                flexShrink: 0,
                fontSize: '1rem',
                borderRadius: '8px',
                color: text,
                border: `1px solid ${chipBorder}`,
                background: chipBg,
              }}
            >
              {renderIcon(item.icon)}
            </Flex>
            <Div
              sx={{
                fontSize: '0.92rem',
                fontWeight: 600,
                color: text,
                whiteSpace: 'nowrap',
                overflow: 'hidden',
                textOverflow: 'ellipsis',
              }}
            >
              {item.label ?? item.search_name}
            </Div>
          </Flex>

          <Flex aCenter gap={1} sx={{ flexShrink: 0, color: muted }}>
            {item.shortcut && (
              <Flex aCenter gap={0.5}>
                {item.shortcut.split('→').map((k, i) => (
                  <Kbd key={i}>{k.trim()}</Kbd>
                ))}
              </Flex>
            )}
            {active ? (
              <Div
                sx={{
                  px: 0.75,
                  py: 0.25,
                  fontSize: '0.8rem',
                  lineHeight: 1,
                  borderRadius: '6px',
                  color: text,
                  border: `1px solid ${activeBorder}`,
                }}
              >
                ↵
              </Div>
            ) : (
              !item.hideArrow && <GoArrowUpRight size={16} />
            )}
          </Flex>
        </>
      )}
    </Item>
  );
}

function renderIcon(icon: OmniSearchItemType['icon']) {
  if (!icon) return null;
  return isValidElement(icon) ? icon : createElement(icon as any);
}
