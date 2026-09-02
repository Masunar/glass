import AsyncRequest from './AsyncRequest';
import CallbackRequest from './CallbackRequest';
import Request from './Request';
import type { AxiosResponse } from 'axios';

export type Response = AxiosResponse & {
  success: boolean;
};

export type ResponseContent = {
  status?: string;
  data: any;
} & Record<string, any>;

export type ResponseProps<T> = {
  content: T;
  response: Response;
  error?: Error;
};

export type ResponseCallback<T> = {
  (props: ResponseProps<T>): void;
};

export type StaticCallable = any;

export { Request, AsyncRequest, CallbackRequest };
