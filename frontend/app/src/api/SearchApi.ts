import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export type SearchHit = {
  id: number;
  title: string;
  subtitle: string;
  path: string;
};

export type SearchGroup = {
  key: string;
  label: string;
  /** Klucz modułu — grupa dostaje ten sam kolor, co listwa nawigacji. */
  module: string;
  hits: SearchHit[];
};

export class SearchApi extends ApiRequest {
  static prefix: string = '/search';

  /** Poniżej dwóch znaków serwer i tak nie odpowiada wynikami. */
  public static readonly minLength = 2;

  public static async query(
    q: string,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get('', { q });
  }
}
