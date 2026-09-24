import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

/** Ustawienia ekranu zapamiętane przy koncie — lista kluczy stoi na serwerze. */
export class PreferencesApi extends ApiRequest {
  static prefix: string = '/preferences';

  public static async save(
    values: Record<string, number>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put('', values);
  }
}
