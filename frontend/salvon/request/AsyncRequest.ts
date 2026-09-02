import Request from './Request';
import { type ResponseProps } from './index';
import { type AxiosRequestConfig, type Method } from 'axios';

export default class AsyncRequest extends Request {
  public static async get(
    url: string,
    payload?: any,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<ResponseProps<any>> {
    return this.request('get', url, payload, undefined, options);
  }

  public static async post(
    url: string,
    payload?: any,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<ResponseProps<any>> {
    return this.request('post', url, payload, undefined, options);
  }

  public static async put(
    url: string,
    payload?: any,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<ResponseProps<any>> {
    return this.request('put', url, payload, undefined, options);
  }

  public static async patch(
    url: string,
    payload?: any,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<ResponseProps<any>> {
    return this.request('patch', url, payload, undefined, options);
  }

  public static async options(
    url: string,
    payload?: any,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<ResponseProps<any>> {
    return this.request('options', url, payload, undefined, options);
  }

  public static async delete(
    url: string,
    payload?: any,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<ResponseProps<any>> {
    return this.request('delete', url, payload, undefined, options);
  }

  public static async blob(
    method: Method,
    url: string,
    payload?: any,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<ResponseProps<Blob>> {
    return this.blobRequest(method, url, payload, undefined, options);
  }
}
