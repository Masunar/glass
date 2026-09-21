import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export type StockRow = {
  product_id: number;
  code: string | null;
  name: string;
  group: string | null;
  finish: string | null;
  /** Stan fizyczny — to, co leży na półce. */
  quantity: number;
  /** Obiecane zleceniom. Może przekroczyć stan i wtedy to sygnał. */
  reserved: number;
  available: number;
  min: number;
  max: number;
  /**
   * `max(0, Max − Stan)` gdy `Stan < Min`. W starym systemie kolumna
   * nazywała się „Produkcja" i nie miała z produkcją nic wspólnego.
   */
  to_order: number;
  is_made_to_order: boolean;
};

export type StockBoard = {
  rows: StockRow[];
  summary: {
    shown: number;
    /** Liczone z całego magazynu, nie z przefiltrowanej listy. */
    to_order: number;
  };
};

export type DemandRow = {
  order_id: number;
  order_number: number;
  contractor: string | null;
  status: string | null;
  product_id: number;
  code: string | null;
  name: string;
  finish: string | null;
  needed: number;
  in_stock: number;
  missing: number;
};

export type DemandBoard = {
  rows: DemandRow[];
  summary: { shown: number; missing: number };
};

export class WarehouseApi extends ApiRequest {
  static prefix: string = '/warehouse';

  public static async levels(
    query: string = '',
    shortages: boolean = false,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get('/levels', { q: query, shortages });
  }

  public static async demand(): Promise<ResponseProps<ResponseContent>> {
    return await this.get('/demand', {});
  }

  /** Progi zamówienia — decyzja człowieka, więc ustawiane wprost. */
  public static async thresholds(
    productId: number,
    min: number,
    max: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put(`/${productId}/thresholds`, { min, max });
  }

  /** Inwentaryzacja. Zapisuje różnicę jako ruch, nie nadpisuje stanu. */
  public static async count(
    productId: number,
    counted: number,
    note: string = '',
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/${productId}/count`, { counted, note });
  }
}
