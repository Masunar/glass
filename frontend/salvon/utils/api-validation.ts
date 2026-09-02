import type { TFunction } from 'i18next';
import type { UseFormSetError } from 'react-hook-form';

import { apiStatus } from '@salvon/consts/api-status';
import type { ResponseContent, ResponseProps } from '@salvon/request';
import type { Noop } from '@salvon/types';
import { isBadRequestScope, isThrottled } from '@salvon/utils/api';
import { notifyError, notifyWarning } from '@salvon/utils/notify';

export const validationFailed = (content: ResponseContent) => {
  return content.status === apiStatus.validation_error && content.data;
};

export const assignValidationMessages = (
  data: any,
  setError: UseFormSetError<any>,
  t: TFunction,
) => {
  Object.keys(data).forEach((key) => {
    setError(key, {
      type: 'validation',
      message: extractMessage(data[key], t),
    });
  });
};

export const extractMessage = (data: any, t: TFunction) => {
  return Object.values(data)
    .map((message: any) => t(message))
    .join(' | ');
};

export const validationCompleted = (
  content: ResponseContent,
  setError: UseFormSetError<any>,
  t: TFunction,
): boolean => {
  if (validationFailed(content)) {
    assignValidationMessages(content.data, setError, t);
    notifyWarning(t('api.validation'));
    return false;
  }

  return true;
};

export const failedResponse = (
  response: ResponseProps<any>,
  setError: UseFormSetError<any>,
  t: TFunction,
  validate?: () => boolean,
) => {
  if (!validationCompleted(response.content, setError, t)) {
    return true;
  }

  if (isThrottled(response.response)) {
    notifyError(t('api.throttle'));
    return;
  }

  if (validate && validate()) {
    return true;
  }

  if (!response.response.success) {
    notifyError(t('api.ise'));
    return true;
  }

  return false;
};
