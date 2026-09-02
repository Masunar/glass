import { ClickAwayListener, Drawer } from '@mui/material';

import TaskDrawerFields from './TaskDrawerFields';
import TaskDrawerHeader from './TaskDrawerHeader';
import type { TaskDrawerProps } from './types.d';
import { useImperativeHandle } from 'react';

import { Div, Flex } from '@salvon/components/div';
import { useIsDarkMode } from '@salvon/hooks/useTheme';

const PORTAL_SELECTORS = [
  '[role="listbox"]',
  '[role="menu"]',
  '[role="tooltip"]',
  '[role="dialog"]',
  '[data-sonner-toast]',
  '.MuiPickersPopper-root',
].join(', ');

export default function TaskDrawer({
  open,
  setOpen,
  onExited,
  closeOnClickAway = true,
  titleIcon,
  title,
  headerActions,
  fields,
  children,
  footer,
  side = 'right',
  width = 680,
  slotProps,
  ref,
}: TaskDrawerProps) {
  const isDark = useIsDarkMode();

  const openDrawer = () => setOpen(true);
  const closeDrawer = () => setOpen(false);

  useImperativeHandle(ref, () => ({ openDrawer, closeDrawer, open }));

  const { sx: paperSx, ...paperRest } = slotProps?.paper ?? {};
  const { sx: rootSx, ...rootRest } = slotProps?.root ?? {};
  const { sx: headerSx, ...headerRest } = slotProps?.header ?? {};
  const { sx: bodySx, ...bodyRest } = slotProps?.body ?? {};

  return (
    <Drawer
      anchor={side}
      open={open}
      onClose={closeDrawer}
      hideBackdrop
      transitionDuration={{ enter: 260, exit: 220 }}
      slotProps={{
        transition: { onExited },
        paper: {
          ...paperRest,
          sx: {
            height: '100%',
            width: { xs: '100%', sm: width },
            boxSizing: 'border-box',
            display: 'flex',
            flexDirection: 'column',
            overflow: 'hidden',
            borderLeft: '1px solid',
            borderColor: isDark ? 'divider' : '#e2e8f0',
            boxShadow: 0,
            ...paperSx,
          },
        },
      }}
    >
      <ClickAwayListener
        mouseEvent="onMouseDown"
        onClickAway={(event) => {
          if (!closeOnClickAway || !open) return;
          const target = event.target as HTMLElement;
          if (target.closest?.(PORTAL_SELECTORS)) return;
          closeDrawer();
        }}
      >
        <Flex column fw {...rootRest} sx={{ height: '100%', ...rootSx }}>
          <Flex
            column
            fw
            {...headerRest}
            sx={{ px: '22px', pt: '18px', flexShrink: 0, ...headerSx }}
          >
            <TaskDrawerHeader
              onClose={closeDrawer}
              titleIcon={titleIcon}
              title={title}
              headerActions={headerActions}
            />
          </Flex>

          <Flex
            column
            fw
            {...bodyRest}
            sx={{
              gap: 1.5,
              flex: 1,
              minHeight: 0,
              overflowY: 'auto',
              overflowX: 'hidden',
              px: 3,
              pt: 1.5,
              pb: 1.5,
              ...bodySx,
            }}
          >
            {fields && fields.length > 0 && (
              <TaskDrawerFields fields={fields} slotProps={slotProps?.fields} />
            )}
            {children}
          </Flex>

          {footer && <Div sx={{ flexShrink: 0 }}>{footer}</Div>}
        </Flex>
      </ClickAwayListener>
    </Drawer>
  );
}
