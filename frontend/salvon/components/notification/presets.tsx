import { notify } from './index';
import type { NotifyProps } from './types.d';
import {
  PiCheckCircleFill,
  PiInfoFill,
  PiWarningCircleFill,
  PiXCircleFill,
} from 'react-icons/pi';

type DistributiveOmit<T, K extends PropertyKey> = T extends unknown
  ? Omit<T, K>
  : never;

type NotifyOptions = DistributiveOmit<NotifyProps, 'message' | 'description'>;

export const notifySuccess = (
  message: string,
  description?: string,
  options?: NotifyOptions,
) =>
  notify({
    accentColor: 'success.main',
    icon: {
      icon: <PiCheckCircleFill />,
      color: '#fff',
      bgcolor: 'success.main',
    },
    ...(options as any),
    message,
    description,
  });

export const notifyError = (
  message: string,
  description?: string,
  options?: NotifyOptions,
) =>
  notify({
    accentColor: 'error.main',
    icon: {
      icon: <PiXCircleFill />,
      color: '#fff',
      bgcolor: 'error.main',
    },
    ...(options as any),
    message,
    description,
  });

export const notifyWarning = (
  message: string,
  description?: string,
  options?: NotifyOptions,
) =>
  notify({
    accentColor: 'warning.main',
    icon: {
      icon: <PiWarningCircleFill />,
      color: '#fff',
      bgcolor: 'warning.main',
    },
    ...(options as any),
    message,
    description,
  });

export const notifyInfo = (
  message: string,
  description?: string,
  options?: NotifyOptions,
) =>
  notify({
    accentColor: 'info.main',
    icon: {
      icon: <PiInfoFill />,
      color: '#fff',
      bgcolor: 'info.main',
    },
    ...(options as any),
    message,
    description,
  });
