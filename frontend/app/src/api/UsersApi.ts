import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export class UsersApi extends ApiRequest {
  static prefix: string = '/users';

  public static async roles(
    data: any = {},
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get('/roles', data);
  }
}
