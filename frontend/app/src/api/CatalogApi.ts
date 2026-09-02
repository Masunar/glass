import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export class CatalogApi extends ApiRequest {
  static prefix: string = '/catalog';

  public static async saveProduct(
    data: Record<string, unknown>,
    productId?: number | null,
  ): Promise<ResponseProps<ResponseContent>> {
    return productId
      ? await this.put(`/products/${productId}`, data)
      : await this.post('/products', data);
  }

  public static async saveGroup(
    data: Record<string, unknown>,
    groupId?: number | null,
  ): Promise<ResponseProps<ResponseContent>> {
    return groupId
      ? await this.put(`/groups/${groupId}`, data)
      : await this.post('/groups', data);
  }
}
