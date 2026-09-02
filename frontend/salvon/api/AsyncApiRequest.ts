import type { AxiosRequestConfig } from 'axios';

import {
  AsyncRequest,
  type ResponseContent,
  type ResponseProps,
} from '@salvon/request';

export default class AsyncApiRequest extends AsyncRequest {
  protected static withCredentials: boolean = true;
  protected static withXSRFToken: boolean = true;
  protected static throwNonResponseErrors: boolean = false;

  public static async create(
    data: any,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post('', data, options);
  }

  public static async read(
    id: number,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/${id}`, {}, options);
  }

  public static async update(
    id: number,
    data: any,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put(`/${id}`, data, options);
  }

  public static async remove(
    id: number,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.delete(`/${id}`, {}, options);
  }

  public static async list(
    data: any = {},
    options?: Partial<AxiosRequestConfig>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get('', data, options);
  }
}
