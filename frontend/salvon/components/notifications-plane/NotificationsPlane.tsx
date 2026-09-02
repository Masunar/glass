import { Badge, Collapse, Typography } from '@mui/material';

import NotificationCard from './NotificationCard';
import type {
  NotificationItem as Item,
  NotificationsPlaneProps,
} from './types.d';
import { type MouseEvent, useMemo, useState } from 'react';
import { MdExpandMore } from 'react-icons/md';
import {
  PiArrowClockwise,
  PiBell,
  PiBellRinging,
  PiEnvelopeSimple,
} from 'react-icons/pi';

import { Div, Flex } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';
import { Popover } from '@salvon/components/popover';
import { useIsUnder } from '@salvon/hooks/useMediaQuery';

export default function NotificationsPlane({
  items,
  count,
  readTotal,
  loading = false,
  hasMore = false,
  loadingMore = false,
  onOpen,
  onRefresh,
  onMarkAllRead,
  onMarkRead,
  onLoadMore,
  onItemClick,
  markReadDelay = 550,
  defaultNewOpen = true,
  defaultReadOpen = false,
  width = 410,
  trigger,
  renderItem,
  labels,
  slotProps,
  panelPlacement = 'bottom-right',
}: NotificationsPlaneProps) {
  const isMobile = useIsUnder('sm');
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null);
  const [newOpen, setNewOpen] = useState(defaultNewOpen);
  const [readOpen, setReadOpen] = useState(defaultReadOpen);

  const { newItems, readItems } = useMemo(() => {
    const next: { newItems: Item[]; readItems: Item[] } = {
      newItems: [],
      readItems: [],
    };
    for (const item of items) {
      (item.read ? next.readItems : next.newItems).push(item);
    }
    return next;
  }, [items]);

  const unreadCount = count ?? newItems.length;
  const readCount = readTotal ?? readItems.length;
  const hasUnread = unreadCount > 0;
  const open = Boolean(anchorEl);
  const loadingLabel = labels?.loading ?? 'Loading…';

  const handleOpen = (event: MouseEvent<HTMLElement>) => {
    setAnchorEl(event.currentTarget);
    onOpen?.();
  };
  const close = () => setAnchorEl(null);

  const renderRow = (item: Item) =>
    renderItem ? (
      <Div key={item.id}>
        {renderItem(item, {
          close,
          markRead: () => onMarkRead?.(item.id),
          marking: false,
        })}
      </Div>
    ) : (
      <NotificationCard
        key={item.id}
        item={item}
        labels={labels}
        markReadDelay={markReadDelay}
        onMarkRead={onMarkRead}
        onItemClick={onItemClick}
        close={close}
        slotProps={slotProps?.item}
      />
    );

  const { sx: badgeSx, ...badgeRest } = slotProps?.badge ?? {};
  const { sx: popoverSx, ...popoverRest } = slotProps?.popover ?? {};
  const { sx: paperSx, ...paperRest } = slotProps?.paper ?? {};
  const { sx: panelSx, ...panelRest } = slotProps?.panel ?? {};
  const { sx: headerSx, ...headerRest } = slotProps?.header ?? {};
  const { sx: listSx, ...listRest } = slotProps?.list ?? {};

  return (
    <Div>
      {trigger ? (
        trigger({ onClick: handleOpen, count: unreadCount, hasUnread, loading })
      ) : (
        <Badge
          color="error"
          badgeContent={unreadCount}
          {...badgeRest}
          sx={{ '& .MuiBadge-badge': { mt: 0.5, mr: 0.5 }, ...badgeSx }}
        >
          <IconButton
            onClick={handleOpen}
            label={labels?.title}
            disabled={loading}
            icon={hasUnread ? <PiBellRinging /> : <PiBell />}
          />
        </Badge>
      )}

      <Popover
        open={open}
        anchorEl={anchorEl}
        onClose={close}
        {...popoverRest}
        placement={panelPlacement}
        slotProps={{
          root: { sx: { mt: 1, mr: isMobile ? 0 : 2, ...popoverSx } },
          paper: {
            ...paperRest,
            sx: {
              borderRadius: '12px',
              boxShadow: 0,
              overflow: 'hidden',
              border: '1px solid',
              pb: 1,
              borderColor: (theme) =>
                theme.palette.mode === 'dark' ? 'divider' : '#e2e8f0',
              ...paperSx,
            },
          },
        }}
      >
        <Div
          {...panelRest}
          sx={{
            width: isMobile ? 'calc(100vw - 24px)' : width,
            maxWidth: '92vw',
            ...panelSx,
          }}
        >
          <Div
            {...headerRest}
            sx={{
              py: '16px',
              px: '18px',
              borderBottom: '1px solid',
              borderColor: 'divider',
              ...headerSx,
            }}
          >
            <Flex jBetween aCenter>
              <Typography
                sx={{
                  fontSize: '1.15rem',
                  fontWeight: 700,
                  color: 'text.primary',
                }}
              >
                {labels?.title ?? 'Notifications'}
              </Typography>

              {onRefresh && (
                <Flex
                  aCenter
                  jCenter
                  onClick={loading ? undefined : onRefresh}
                  sx={{
                    width: isMobile ? 44 : 32,
                    height: isMobile ? 44 : 32,
                    borderRadius: '8px',
                    border: '1px solid',
                    borderColor: 'divider',
                    color: 'text.secondary',
                    cursor: loading ? 'default' : 'pointer',
                    opacity: loading ? 0.5 : 1,
                    transition: 'all 0.15s',
                    '&:hover': {
                      background: (theme) => theme.palette.action.hover,
                      color: 'primary.main',
                    },
                  }}
                >
                  <PiArrowClockwise />
                </Flex>
              )}
            </Flex>

            {labels?.summary && (
              <Typography
                sx={{ fontSize: '0.8rem', color: 'text.secondary', mt: '2px' }}
              >
                {loading ? loadingLabel : labels.summary(unreadCount)}
              </Typography>
            )}

            {hasUnread && onMarkAllRead && (
              <Flex
                aCenter
                gap={0.75}
                onClick={onMarkAllRead}
                sx={{
                  mt: '11px',
                  py: isMobile ? '6px' : 0,
                  width: 'fit-content',
                  cursor: 'pointer',
                  color: 'primary.main',
                  fontSize: '0.8rem',
                  fontWeight: 600,
                  '&:hover': { opacity: 0.7 },
                }}
              >
                <PiEnvelopeSimple size={15} />
                <span>{labels?.markAllRead ?? 'Mark all as read'}</span>
              </Flex>
            )}
          </Div>

          <Div
            {...listRest}
            sx={{
              maxHeight: isMobile ? 'calc(100dvh - 220px)' : 460,
              overflow: 'auto',
              ...listSx,
            }}
          >
            {loading ? (
              <Typography
                sx={{
                  fontSize: '0.85rem',
                  color: 'text.secondary',
                  textAlign: 'center',
                  py: 3,
                }}
              >
                {loadingLabel}
              </Typography>
            ) : (
              <>
                <SectionHeader
                  label={`${labels?.newSection ?? 'New'} (${newItems.length})`}
                  open={newOpen}
                  onToggle={() => setNewOpen((v) => !v)}
                  isMobile={isMobile}
                />
                <Collapse in={newOpen} timeout="auto" unmountOnExit>
                  {newItems.length > 0 ? (
                    newItems.map(renderRow)
                  ) : (
                    <Typography
                      sx={{
                        fontSize: '0.8rem',
                        color: 'text.secondary',
                        textAlign: 'center',
                        py: 1.5,
                      }}
                    >
                      {labels?.noNew ?? 'No new notifications'}
                    </Typography>
                  )}
                </Collapse>

                {readCount > 0 && (
                  <>
                    <SectionHeader
                      label={`${labels?.readSection ?? 'Read'} (${readCount})`}
                      open={readOpen}
                      onToggle={() => setReadOpen((v) => !v)}
                      isMobile={isMobile}
                    />
                    <Collapse in={readOpen} timeout="auto" unmountOnExit>
                      {readItems.map(renderRow)}
                      {hasMore && onLoadMore && (
                        <Flex
                          jCenter
                          onClick={loadingMore ? undefined : onLoadMore}
                          sx={{
                            py: '10px',
                            cursor: loadingMore ? 'default' : 'pointer',
                            color: 'primary.main',
                            fontSize: '0.78rem',
                            fontWeight: 600,
                            opacity: loadingMore ? 0.6 : 1,
                            '&:hover': { opacity: loadingMore ? 0.6 : 0.7 },
                          }}
                        >
                          {loadingMore
                            ? loadingLabel
                            : (labels?.showMore ?? 'Show more')}
                        </Flex>
                      )}
                    </Collapse>
                  </>
                )}
              </>
            )}
          </Div>
        </Div>
      </Popover>
    </Div>
  );
}

function SectionHeader({
  label,
  open,
  onToggle,
  isMobile,
}: {
  label: string;
  open: boolean;
  onToggle: () => void;
  isMobile: boolean;
}) {
  return (
    <Flex
      aCenter
      gap={1}
      onClick={onToggle}
      sx={{
        pt: isMobile ? '16px' : '13px',
        pb: isMobile ? '12px' : '5px',
        px: '18px',
        cursor: 'pointer',
      }}
    >
      <Typography
        sx={{
          fontSize: '0.7rem',
          fontWeight: 700,
          letterSpacing: '0.07em',
          color: 'text.secondary',
          textTransform: 'uppercase',
          whiteSpace: 'nowrap',
        }}
      >
        {label}
      </Typography>
      <Div sx={{ flex: 1, height: '1px', bgcolor: 'divider' }} />
      <MdExpandMore
        style={{
          fontSize: 16,
          transition: 'transform 200ms',
          transform: open ? 'rotate(180deg)' : undefined,
        }}
      />
    </Flex>
  );
}
