import AlternativeNotification from './alternative';
import BaseNotification from './base';
import { type NotifyProps } from './types.d';
import { type JSX, createElement } from 'react';

import { type RenderOptions, toast } from '@salvon/utils/toast';

const variants = {
  base: BaseNotification,
  alternative: AlternativeNotification,
} as const;

const defaultDuration = 5000;

const now = () =>
  new Date().toLocaleTimeString('pl-PL', {
    hour: '2-digit',
    minute: '2-digit',
  });

export const closeNotification = (id: number | string) => toast.dismiss(id);

const notification = (
  render: (id: string | number) => JSX.Element,
  options?: RenderOptions,
) => toast.custom(render, options);

export const notify = ({ variant = 'alternative', ...props }: NotifyProps) =>
  notification(
    (id) =>
      createElement(variants[variant], {
        autoclose: defaultDuration,
        ...(variant === 'alternative' && { time: now() }),
        ...props,
        id,
        onClose: () => closeNotification(id),
      }),
    { duration: Infinity },
  );

export {
  notifySuccess,
  notifyError,
  notifyWarning,
  notifyInfo,
} from './presets';
