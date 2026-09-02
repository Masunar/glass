import Request from './Request';
import { type ResponseCallback, type ResponseContent } from './index';
import { type AxiosRequestConfig, type Method } from 'axios';

export default class CallbackRequest extends Request {
  public static async get(
    url: string,
    payload?: any,
    callback?: ResponseCallback<any>,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<void> {
    await this.request('get', url, payload, callback, options);
  }

  public static async post(
    url: string,
    payload?: any,
    callback?: ResponseCallback<any>,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<void> {
    await this.request('post', url, payload, callback, options);
  }

  public static async put(
    url: string,
    payload?: any,
    callback?: ResponseCallback<any>,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<void> {
    await this.request('put', url, payload, callback, options);
  }

  public static async patch(
    url: string,
    payload?: any,
    callback?: ResponseCallback<any>,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<void> {
    await this.request('patch', url, payload, callback, options);
  }

  public static async options(
    url: string,
    payload?: any,
    callback?: ResponseCallback<any>,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<void> {
    await this.request('options', url, payload, callback, options);
  }

  public static async delete(
    url: string,
    payload?: any,
    callback?: ResponseCallback<any>,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<void> {
    await this.request('delete', url, payload, callback, options);
  }

  public static async blob(
    method: Method,
    url: string,
    payload?: any,
    callback?: ResponseCallback<Blob>,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<void> {
    await this.blobRequest(method, url, payload, callback, options);
  }
}
