import { type ReactNode } from 'react';

export type NotificationIconConfig = {
  icon: ReactNode;
  color: string;
  bgcolor: string;
};

type NotificationCommonProps = {
  autoclose?: number;
  message?: ReactNode;
  description?: ReactNode;
  accentColor?: string;
  icon?: NotificationIconConfig;
  footer?: (props: { close: () => void }) => ReactNode;
};

type BaseVariantProps = {
  variant: 'base';
  accentBar?: boolean;
};

type AlternativeVariantProps = {
  variant?: 'alternative';
  time?: ReactNode;
  barPosition?: 'inline' | 'top' | 'bottom';
  rightBar?: (props: { close: () => void }) => ReactNode;
};

export type NotifyProps = NotificationCommonProps &
  (BaseVariantProps | AlternativeVariantProps);
