import { Typography, type TypographyProps } from '@mui/material';

import CloseNotification from './CloseNotification';
import NotificationAccent from './NotificationAccent';
import NotificationIcon from './NotificationIcon';
import { type ReactNode } from 'react';

import { Card } from '@salvon/components/card';
import { Div, Flex } from '@salvon/components/div';

export type NotificationIconConfig = {
  icon: ReactNode;
  color: string;
  bgcolor: string;
};

export type NotificationProps = {
  id: number | string;
  autoclose?: number;
  message?: ReactNode;
  description?: ReactNode;
  accentColor?: string;
  accentBar?: boolean;
  icon?: NotificationIconConfig;
  onClose: () => void;
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

export default function BaseNotification({
  message,
  description,
  icon,
  accentColor,
  onClose,
  footer,
  autoclose,
  accentBar = true,
}: NotificationProps) {
  return (
    <Card
      sx={{
        minWidth: { xs: '100%', md: 370 },
        maxWidth: { xs: '100%', md: 460 },
        padding: icon ? '8px 12px 8px 8px' : '8px 12px 8px 12px',
      }}
    >
      <Flex gap={1.5} align="top">
        {accentBar && <NotificationAccent color={accentColor} />}

        <Div fw>
          <Flex fw align="center" gap={1} sx={{ padding: '6px 0' }}>
            {icon && (
              <NotificationIcon
                icon={icon.icon}
                color={icon.color}
                bg={icon.bgcolor}
              />
            )}

            <Div sx={{ flex: 1, minWidth: 0, py: 0.25, lineHeight: '1.1' }}>
              {withTypography(message, {
                variant: 'body1',
                sx: {
                  fontWeight: 700,
                  color: 'text.primary',
                  lineHeight: 1.2,
                  fontSize: '0.88rem',
                },
              })}
              {withTypography(description, {
                variant: 'body2',
                sx: {
                  color: 'text.secondary',
                  lineHeight: 1,
                  fontSize: '0.8rem',
                },
              })}
            </Div>
            <CloseNotification
              onClose={onClose}
              autoclose={autoclose}
              color={accentColor}
            />
          </Flex>
          {footer && footer({ close: onClose })}
        </Div>
      </Flex>
    </Card>
  );
}
