import { Popover as MUIPopover } from '@mui/material';

import Entries from '../../Entries';
import ListButton from '../../components/ListButton';
import ListIcon from '../../components/ListIcon';
import type { PopoverDropdown } from './types.d';
import { createElement, useCallback, useRef, useState } from 'react';
import { MdChevronRight, MdExpandMore } from 'react-icons/md';
import { useLocation } from 'react-router';

import { Div, Flex } from '@salvon/components/div';
import { useHasActiveMenuEntry } from '@salvon/hooks/useHasActiveMenuEntry';
import { usePalette } from '@salvon/hooks/useTheme';
import { entryMatchesRegex } from '@salvon/utils/menu';
import { voc } from '@salvon/utils/object';

export default function Popover({
  compactMode,
  items,
  icon,
  title,
  regex,
  isNested = false,
  firstOnClick = true,
  enableHover = true,
  hasPermissionTo,
  ...props
}: PopoverDropdown) {
  const palette = usePalette();
  const [isOpen, setIsOpen] = useState(false);
  const open = useCallback(() => setIsOpen(true), []);
  const close = useCallback(() => setIsOpen(false), []);
  const popoverMenuRef = useRef<HTMLLIElement>(null);
  const location = useLocation();

  const allowHoverEvents = (isNested || !firstOnClick) && enableHover;
  const isActive =
    useHasActiveMenuEntry(items) || entryMatchesRegex(regex, location.pathname);

  return (
    <Div
      ref={popoverMenuRef}
      {...(allowHoverEvents && { onMouseEnter: open, onMouseLeave: close })}
    >
      <ListButton
        className={isActive || isOpen ? 'active' : ''}
        {...props}
        onClick={() => setIsOpen(true)}
      >
        <Flex aCenter fw>
          <Flex>
            {icon && !isNested && <ListIcon>{createElement(icon)}</ListIcon>}
            {!icon && !isNested && title}
            {isNested && title}
          </Flex>
          <Flex
            center
            sx={{
              fontSize: 18,
              ...voc(!isNested, {
                right: 0,
                marginLeft: 0,
                position: 'absolute',
              }),
              marginLeft: 4,
            }}
          >
            {isOpen ? <MdChevronRight /> : <MdExpandMore />}
          </Flex>
        </Flex>
      </ListButton>
      <MUIPopover
        keepMounted
        transitionDuration={170}
        open={isOpen}
        onClose={close}
        disableRestoreFocus
        {...(allowHoverEvents && {
          sx: {
            pointerEvents: 'none',
            '& .MuiList-root': {
              pointerEvents: 'auto',
            },
          },
        })}
        anchorEl={popoverMenuRef.current}
        anchorOrigin={{ vertical: 'center', horizontal: 'right' }}
        transformOrigin={{
          vertical: 'center',
          horizontal: 'left',
        }}
        slotProps={{
          paper: {
            sx: {
              ...(palette.salvon?.popover?.paper ?? {}),
            },
          },
        }}
      >
        <Entries
          isNested={true}
          items={items}
          compactMode={compactMode}
          hasPermissionTo={hasPermissionTo}
          sx={{ minWidth: 130 }}
        />
      </MUIPopover>
    </Div>
  );
}
