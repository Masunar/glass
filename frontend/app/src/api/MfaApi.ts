import type { AxiosRequestConfig } from 'axios';

import { ApiRequest } from '@app/api/ApiRequest';

export class MfaApi extends ApiRequest {
  protected static prefix: string = 'mfa';

  public static async requirement(options?: Partial<AxiosRequestConfig>) {
    return this.get(`/requirement`, {}, options);
  }

  public static async verify(data: any, options?: Partial<AxiosRequestConfig>) {
    return this.post(`/verify`, data, options);
  }

  public static async recoveryAccount(data: { recovery_key: string }) {
    return this.post(`/recovery-account`, data);
  }

  public static async beginSetup() {
    return this.post(`/begin-setup`, {});
  }

  public static async finishSetup(data: any) {
    return this.post(`/finish-setup`, data);
  }

  public static async recoveryKey() {
    return this.get(`/recovery-key`, {});
  }

  public static async disable() {
    return this.post(`/disable`, {});
  }

  public static async beginEmailSetup() {
    return this.post(`/begin-email-setup`, {});
  }

  public static async finishEmailSetup(data: { code: string }) {
    return this.post(`/finish-email-setup`, data);
  }
}
