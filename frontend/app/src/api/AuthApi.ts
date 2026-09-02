import env from '@app_env';

import type { AxiosRequestConfig } from 'axios';

import { ApiRequest } from '@app/api/ApiRequest';

export class AuthApi extends ApiRequest {
  public static async csrf() {
    return this.get(`${env.api_url}/sanctum/csrf-cookie`);
  }

  public static async login(data: any) {
    await this.csrf();
    return this.post(`/auth/login`, data);
  }

  public static async user(options?: Partial<AxiosRequestConfig>) {
    return this.get(`/auth/user`, {}, options);
  }

  public static async forgotPassword(email: string) {
    await this.csrf();
    return this.post(`/auth/forgot-password`, { email });
  }

  public static async resetPassword(data: any) {
    await this.csrf();
    return this.post(`/auth/reset-password`, data);
  }

  public static async changePassword(data: any) {
    return this.post(`/auth/change-password`, data);
  }

  public static async updateProfile(data: any) {
    return this.post(`/auth/update-profile`, data);
  }

  public static async logout() {
    return this.post(`/auth/logout`);
  }

  public static async stopImpersonation() {
    return this.post(`/auth/stop-impersonation`);
  }
}
