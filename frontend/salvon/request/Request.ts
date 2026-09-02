import axios, {
  type AxiosInstance,
  type AxiosRequestConfig,
  type AxiosResponse,
  type InternalAxiosRequestConfig,
  type Method,
} from 'axios';

import type {
  ResponseCallback,
  ResponseProps,
  StaticCallable,
} from '@salvon/request';
import { voc } from '@salvon/utils/object';

export default class Request implements StaticCallable {
  protected static prefix: string = '';
  protected static baseUrl: undefined | string = undefined;
  protected static withCredentials: undefined | boolean = false;
  protected static withXSRFToken: undefined | boolean = false;
  protected static throwNonResponseErrors: boolean = true;
  protected static acceptHeader: string = 'application/json';

  protected static createAxios(): AxiosInstance {
    const locale = this.getStoredLocale();
    const currency = this.getStoredCurrency();
    const headers = {
      Accept: this.acceptHeader,
      ...voc(!!locale, { Locale: locale }),
      ...voc(!!currency, { 'X-Currency': currency }),
    };

    return axios.create({
      baseURL: this.baseUrl,
      withCredentials: this.withCredentials,
      withXSRFToken: this.withXSRFToken,
      headers,
    });
  }

  protected static getStoredLocale(): string | undefined {
    return undefined;
  }

  protected static getStoredCurrency(): string | undefined {
    return undefined;
  }

  protected static createInstance(): AxiosInstance {
    const axios = this.createAxios();

    this.axiosDefaults(axios);
    this.axiosRequestInterceptors(axios);
    this.axiosResponseInterceptors(axios);

    return axios;
  }

  protected static axiosDefaults(axios: AxiosInstance): void {
    axios.defaults.withCredentials = true;
    axios.defaults.withXSRFToken = true;
  }

  protected static axiosRequestInterceptors(axios: AxiosInstance): void {
    axios.interceptors.request.use((config) => {
      if (config.method !== 'get' && !(config.data instanceof FormData)) {
        config.headers['Content-Type'] = 'application/json';
        config.data = JSON.stringify(config.data);
      }

      this.axiosRequestInterceptorsInternalConfig(config);

      return config;
    });
  }

  protected static axiosResponseInterceptors(axios: AxiosInstance): void {
    axios.interceptors.response.use(
      (response) => response,
      (error) => {
        this.axiosResponseInterceptorsOnError(error);
        throw error;
      },
    );
  }

  // eslint-disable-next-line
  protected static axiosResponseInterceptorsOnError(error: any): void {}

  protected static axiosRequestInterceptorsInternalConfig(
    // eslint-disable-next-line
    config: InternalAxiosRequestConfig,
  ): void {}

  protected static async request(
    method: Method,
    url: string,
    payload?: any,
    callback?: ResponseCallback<any>,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<ResponseProps<any>> {
    const urlPrefix = this.prefix;
    let callbackProps: ResponseProps<any>;

    try {
      const instance = this.createInstance();

      const response: AxiosResponse = await instance({
        method,
        url: `${urlPrefix}${url}`,
        params: method === 'get' ? payload : undefined,
        data: method !== 'get' ? payload : undefined,
        ...options,
      });

      const content = response?.data;

      callbackProps = {
        content,
        response: { ...response, success: true },
      };
    } catch (error: any) {
      if (this.throwNonResponseErrors && !error.response) {
        throw error;
      }

      const response = {
        ...error?.response,
        success: false,
      };

      const content = response?.data;

      callbackProps = {
        content,
        response,
        error,
      };

      callback?.(callbackProps);
      return callbackProps;
    }

    callback?.(callbackProps);
    return callbackProps;
  }

  protected static async blobRequest(
    method: Method,
    url: string,
    payload?: any,
    callback?: ResponseCallback<Blob>,
    options?: Partial<AxiosRequestConfig>,
  ): Promise<ResponseProps<Blob>> {
    return this.request(method, url, payload, callback, {
      ...(options ?? {}),
      responseType: 'blob',
    });
  }
}
