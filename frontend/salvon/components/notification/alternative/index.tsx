import { Typography, type TypographyProps } from '@mui/material';

import NotificationIcon from '../base/NotificationIcon';
import AutoCloseBar from './AutoCloseBar';
import CloseNotification from './CloseNotification';
import { type ReactNode } from 'react';

import { Card } from '@salvon/components/card';
import { Div, Flex } from '@salvon/components/div';

export type NotificationIconConfig = {
  icon: ReactNode;
  color: string;
  bgcolor: string;
};

export type AlternativeNotificationProps = {
  id: number | string;
  autoclose?: number;
  time?: ReactNode;
  message?: ReactNode;
  description?: ReactNode;
  icon?: NotificationIconConfig;
  accentColor?: string;
  barPosition?: 'inline' | 'top' | 'bottom';
  onClose: () => void;
  rightBar?: (props: { close: () => void }) => ReactNode;
  footer?: (props: { close: () => void }) => ReactNode;
};

function withTypography(content: ReactNode, props: TypographyProps) {
  if (content == null || content === false) {
    return null;
  }
  if (typeof content === 'string' || typeof content === 'number') {
    return (
      <Typography component="div" {...props}>
        {content}
      </Typography>
    );
  }
  return content;
}

export default function AlternativeNotification({
  time,
  message,
  description,
  icon,
  accentColor,
  barPosition = 'inline',
  onClose,
  rightBar,
  footer,
  autoclose,
}: AlternativeNotificationProps) {
  const showBar = typeof autoclose === 'number';
  const hasBody = !!(description || rightBar || footer);

  return (
    <Card
      sx={{
        minWidth: { xs: '100%', md: 380 },
        maxWidth: { xs: '100%', md: 460 },
        padding: 0,
        overflow: 'hidden',
      }}
    >
      {showBar && barPosition === 'top' && (
        <AutoCloseBar
          noTrack
          full
          autoclose={autoclose}
          color={accentColor}
          onClose={onClose}
        />
      )}

      <Flex
        fw
        aCenter
        gap={1.25}
        sx={{
          padding: '12px 16px',
          ...(hasBody && {
            borderBottom: '1px solid',
            borderColor: 'divider',
          }),
        }}
      >
        {icon && (
          <NotificationIcon
            icon={icon.icon}
            color={icon.color}
            bg={icon.bgcolor}
            size={33}
          />
        )}
        {message && (
          <Typography
            variant="h5"
            sx={{
              fontWeight: 800,
              fontSize: '0.9rem',
              color: 'text.primary',
              minWidth: 0,
              overflow: 'hidden',
              textOverflow: 'ellipsis',
              maxWidth: 150,
            }}
          >
            {message}
          </Typography>
        )}
        <Flex aCenter gap={1.5} sx={{ ml: 'auto' }}>
          {time && (
            <Typography
              variant="body2"
              sx={{
                color: 'text.secondary',
                fontWeight: 600,
                fontSize: '0.8rem',
                minWidth: 35,
              }}
            >
              {time}
            </Typography>
          )}
          {showBar && barPosition === 'inline' && (
            <AutoCloseBar
              autoclose={autoclose}
              color={accentColor}
              onClose={onClose}
            />
          )}
          <CloseNotification onClose={onClose} />
        </Flex>
      </Flex>

      {hasBody && (
        <Div sx={{ padding: '14px 16px 16px' }}>
          <Flex aCenter gap={1.5}>
            <Div sx={{ flex: 1, minWidth: 0 }}>
              {withTypography(description, {
                variant: 'body2',
                sx: {
                  color: 'text.primary',
                  fontWeight: 500,
                  fontSize: '0.82rem',
                },
              })}
            </Div>

            {rightBar && (
              <Div sx={{ flex: '0 0 auto' }}>
                {rightBar({ close: onClose })}
              </Div>
            )}
          </Flex>

          {footer && <Div sx={{ mt: 1 }}>{footer({ close: onClose })}</Div>}
        </Div>
      )}

      {showBar && barPosition === 'bottom' && (
        <AutoCloseBar
          full
          noTrack
          autoclose={autoclose}
          color={accentColor}
          onClose={onClose}
        />
      )}
    </Card>
  );
}
