import type { SxProps, Theme } from '@mui/material';

import { voc } from '@salvon/utils/object';
import { boolVal } from '@salvon/utils/type-transform';

type CellState = {
  selected?: boolean;
  disabled?: boolean;
  muted?: boolean;
  today?: boolean;
};

export function cellSx({
  selected,
  disabled,
  muted,
  today,
}: CellState): SxProps<Theme> {
  return {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: '8px',
    cursor: disabled ? 'default' : 'pointer',
    userSelect: 'none',
    fontSize: '0.8rem',
    fontWeight: selected ? 700 : 500,
    color: selected ? 'primary.contrastText' : 'text.primary',
    backgroundColor: selected ? 'primary.main' : 'transparent',
    transition: 'background-color 0.12s ease, box-shadow 0.12s ease',
    ...voc(!!muted && !selected, { color: 'text.disabled' }),
    ...voc(!!today && !selected, {
      boxShadow: 'inset 0 0 0 1px',
      color: 'primary.main',
    }),
    ...voc(!!disabled, { opacity: 0.4, pointerEvents: 'none' }),
    ...voc(!selected && !disabled, {
      '&:hover': { backgroundColor: 'action.hover' },
    }),
  };
}

type RangeCellState = {
  endpoint?: boolean;
  inRange?: boolean;
  disabled?: boolean;
  muted?: boolean;
  today?: boolean;
  roundLeft?: boolean;
  roundRight?: boolean;
  /** Resolved band fill (primary at low alpha), passed by the caller. */
  bandBg: string;
};

const RADIUS = '8px';

export function rangeCellSx({
  endpoint,
  inRange,
  disabled,
  muted,
  today,
  roundLeft,
  roundRight,
  bandBg,
}: RangeCellState): SxProps<Theme> {
  const banded = endpoint || inRange;
  return {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    height: '100%',
    cursor: disabled ? 'default' : 'pointer',
    userSelect: 'none',
    fontSize: '0.8rem',
    fontWeight: endpoint ? 700 : 500,
    color: muted && !endpoint ? 'text.disabled' : 'text.primary',
    transition: 'background-color 0.12s ease',
    borderRadius: banded ? 0 : RADIUS,
    ...voc(boolVal(banded), { backgroundColor: bandBg }),
    ...voc(!!endpoint, {
      backgroundColor: 'primary.main',
      color: 'primary.contrastText',
    }),
    ...voc(!!roundLeft, {
      borderTopLeftRadius: RADIUS,
      borderBottomLeftRadius: RADIUS,
    }),
    ...voc(!!roundRight, {
      borderTopRightRadius: RADIUS,
      borderBottomRightRadius: RADIUS,
    }),
    ...voc(!!today && !banded, {
      boxShadow: 'inset 0 0 0 1px',
      color: 'primary.main',
    }),
    ...voc(!!disabled, { opacity: 0.4, pointerEvents: 'none' }),
    ...voc(!banded && !disabled, {
      '&:hover': { backgroundColor: 'action.hover' },
    }),
  };
}
