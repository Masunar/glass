import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export type PriceCell = {
  coefficient: string | null;
  net_price: string | null;
  source: 'computed' | 'manual' | null;
  /** Cena zakupu zmieniła się od ostatniego zapisu cennika. */
  is_stale: boolean;
  recomputed_net_price: string | null;
  margin_percent: number | null;
};

export type PriceRow = {
  product_id: number;
  name: string;
  thickness_mm: number | null;
  unit: string;
  purchase_net_price: string | null;
  cells: Record<string, PriceCell>;
};

export type PriceColumn = {
  id: number;
  name: string;
  is_default: boolean;
};

export type PriceMatrix = {
  section: string;
  groups: { id: number; name: string }[];
  group_id: number | null;
  columns: PriceColumn[];
  rows: PriceRow[];
};

export type PriceCellInput = {
  product_id: number;
  price_section_id: number;
  coefficient: string | null;
  manual_net_price: string | null;
};

export class PriceListApi extends ApiRequest {
  static prefix: string = '/price-list';

  public static async matrix(
    section: string,
    groupId?: number | null,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get('', { section, group_id: groupId ?? '' });
  }

  /** Zapis obejmuje wyłącznie zmienione komórki i jest niepodzielny. */
  public static async save(
    cells: PriceCellInput[],
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put('', { cells });
  }
}
