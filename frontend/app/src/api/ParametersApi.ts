import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export type ParameterType =
  | 'number'
  | 'percent'
  | 'text'
  | 'iban'
  | 'template';

export type Parameter = {
  key: string;
  type: ParameterType;
  value: string | null;
  description: string | null;
  valid_from: string;
};

export class ParametersApi extends ApiRequest {
  static prefix: string = '/parameters';

  /** Zapis obejmuje wyłącznie zmienione pola i jest niepodzielny. */
  public static async save(
    values: Record<string, string | null>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put('', { values });
  }
}
