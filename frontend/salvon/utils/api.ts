import { apiStatus } from '@salvon/consts/api-status';
import type { Response, ResponseContent } from '@salvon/request';

export const isUnauthorized = (response: Response) => {
  return response?.status === 401;
};

export const isForbidden = (response: Response) => {
  return response?.status === 403;
};

export const isNotFound = (response: Response) => {
  return response?.status === 404;
};

export const isAccessDenied = (response: Response) => {
  return isForbidden(response) || isUnauthorized(response);
};

export const isBadRequestScope = (response: Response) => {
  return response.status >= 400 && response.status <= 499;
};

export const isInternalServerErrorScope = (response: Response) => {
  return response.status >= 500 && response.status <= 599;
};

export const isFatalResponse = (response: Response) => {
  return (
    isInternalServerErrorScope(response) || [404].includes(response.status)
  );
};

export const isThrottled = (response: Response) => {
  return [429].includes(response.status);
};

export const isStatusBadRequest = (content: ResponseContent) => {
  return content.status === apiStatus.bad_request;
};

export const isMfaRequired = (content: ResponseContent) => {
  return content.status === apiStatus.mfa_required;
};

export const isMfaInvalid = (content: ResponseContent) => {
  return content.status === apiStatus.mfa_invalid;
};
