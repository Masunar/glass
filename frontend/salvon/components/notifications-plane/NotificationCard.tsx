import { type SxProps, Tooltip, Typography } from '@mui/material';

import { formatNotificationDate } from './format';
import type {
  NotificationItem as Item,
  NotificationsPlaneLabel,
} from './types.d';
import { type MouseEvent, useState } from 'react';
import { PiBell, PiCheckCircle } from 'react-icons/pi';

import { Button } from '@salvon/components/button';
import { Div, Flex } from '@salvon/components/div';
import type { FlexProps } from '@salvon/components/div';
import { Link } from '@salvon/components/navigation';
import type { SlotItem } from '@salvon/types';

type Props = {
  item: Item;
  labels?: NotificationsPlaneLabel;
  markReadDelay: number;
  onMarkRead?: (id: string) => void;
  onItemClick?: (item: Item) => void;
  close: () => void;
  slotProps?: SlotItem<FlexProps>;
};

export default function NotificationCard({
  item,
  labels,
  markReadDelay,
  onMarkRead,
  onItemClick,
  close,
  slotProps,
}: Props) {
  const [marking, setMarking] = useState(false);
  const isUnread = !item.read;
  const color = item.color ?? {};
  const { sx, ...rest } = slotProps ?? {};

  const markRead = () => {
    if (marking || !isUnread || !onMarkRead) return;
    setMarking(true);
    window.setTimeout(() => onMarkRead(item.id), markReadDelay);
  };

  const handleGoto = (event: MouseEvent) => {
    event.stopPropagation();
    if (marking) return;
    onItemClick?.(item);
    item.onOpen?.();
    close();
  };

  const tooltip = isUnread && !marking ? (labels?.markReadHint ?? '') : '';
  const gotoText = item.gotoLabel ?? labels?.goto ?? 'Open →';
  const gotoSx: SxProps = {
    fontSize: '0.72rem',
    fontWeight: 600,
    color: 'primary.main',
    cursor: 'pointer',
    whiteSpace: 'nowrap',
    '&:hover': { opacity: 0.6 },
  };

  return (
    <Tooltip title={tooltip} placement="top" arrow enterDelay={400}>
      <Flex
        onClick={markRead}
        gap={1.5}
        {...rest}
        sx={{
          py: '12px',
          px: '18px',
          cursor: marking ? 'default' : 'pointer',
          pointerEvents: marking ? 'none' : 'auto',
          transition: 'background-color 0.15s ease-in-out',
          '&:hover': { bgcolor: marking ? undefined : 'action.hover' },
          ...sx,
        }}
      >
        <Flex
          aCenter
          jCenter
          sx={{
            flexShrink: 0,
            width: 36,
            height: 36,
            borderRadius: '9px',
            bgcolor: color.bg ?? 'action.hover',
            color: color.fg ?? 'text.secondary',
            opacity: isUnread ? 1 : 0.7,
          }}
        >
          {item.icon ?? <PiBell size={18} />}
        </Flex>

        <Div sx={{ flex: 1, minWidth: 0 }}>
          <Flex jBetween gap={1} sx={{ alignItems: 'flex-start' }}>
            <Typography
              sx={{
                fontSize: '0.88rem',
                lineHeight: 1.35,
                fontWeight: isUnread ? 600 : 500,
                color: isUnread ? 'text.primary' : 'text.secondary',
                flex: 1,
                minWidth: 0,
              }}
            >
              {item.title}
            </Typography>
            {isUnread && (
              <Div
                sx={{
                  width: 8,
                  height: 8,
                  borderRadius: '50%',
                  bgcolor: 'primary.main',
                  flexShrink: 0,
                  mt: '5px',
                }}
              />
            )}
          </Flex>

          {item.description && (
            <Typography
              sx={{
                fontSize: '0.79rem',
                lineHeight: 1.35,
                color: 'text.secondary',
                mt: '2px',
                wordBreak: 'break-word',
                overflowWrap: 'anywhere',
              }}
            >
              {item.description}
            </Typography>
          )}

          <Flex jBetween aCenter gap={1} sx={{ mt: '5px' }}>
            {marking ? (
              <Flex aCenter gap={0.5} sx={{ color: 'success.main' }}>
                <PiCheckCircle size={13} />
                <Typography sx={{ fontSize: '0.72rem', fontWeight: 600 }}>
                  {labels?.markedRead ?? 'Marked as read'}
                </Typography>
              </Flex>
            ) : (
              <Typography
                sx={{
                  fontSize: '0.72rem',
                  color: 'text.disabled',
                  whiteSpace: 'nowrap',
                }}
              >
                {formatNotificationDate(item.date, labels)}
              </Typography>
            )}

            <Flex aCenter gap={1.25} sx={{ flexShrink: 0 }}>
              {item.actions?.map((action, index) => (
                <Button
                  key={index}
                  variant="text"
                  color="primary"
                  size="small"
                  disabled={action.disabled}
                  sx={{
                    minWidth: 0,
                    p: 0,
                    fontSize: '0.72rem',
                    fontWeight: 600,
                    whiteSpace: 'nowrap',
                    '&:hover': { opacity: 0.6 },
                  }}
                  onClick={(event) => {
                    event.stopPropagation();
                    action.onClick();
                    close();
                  }}
                >
                  {action.label}
                </Button>
              ))}

              {item.path ? (
                <Link href={item.path} onClick={handleGoto} sx={gotoSx}>
                  {gotoText}
                </Link>
              ) : (
                item.onOpen && (
                  <Typography onClick={handleGoto} sx={gotoSx}>
                    {gotoText}
                  </Typography>
                )
              )}
            </Flex>
          </Flex>
        </Div>
      </Flex>
    </Tooltip>
  );
}
