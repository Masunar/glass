import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export type TemperingRow = {
  id: number;
  order_id: number;
  order_number: number;
  contractor: string | null;
  name: string;
  thickness_mm: number | null;
  width_mm: number;
  height_mm: number;
  quantity: number;
  kg: number;
  m2: number;
  is_irregular_shape: boolean;
  /** Znak z formatki. Co fizycznie oznacza — H-01, nadal otwarte. */
  needs_mark: boolean;
  note: string | null;
  status: string;
  status_label: string;
  /** Pozycja zastępcza wskazuje tę, która się stłukła. */
  replaces_id: number | null;
  batch_number: number | null;
  /**
   * Udział pozycji w koszcie partii, liczony po m². To **koszt**,
   * nie cena — klient płaci za hartowanie z cennika procesu H.
   * Pojawia się dopiero po rozliczeniu partii.
   */
  cost_share?: string;
};

export type TemperingQueue = {
  rows: TemperingRow[];
  /** Grubości obecne w kolejce — piec ustawia się pod jedną. */
  thicknesses: number[];
  summary: { shown: number; kg: number; m2: number };
};

export type TemperingBatchRow = {
  id: number;
  number: number;
  supplier: string;
  status: string;
  status_label: string;
  is_open: boolean;
  is_editable: boolean;
  sent_at: string | null;
  expected_at: string | null;
  returned_at: string | null;
  /** Dni poza zakładem — jedyna liczba mówiąca, czy się spóźnia. */
  days_out: number | null;
  net_cost: string | null;
  document: string | null;
  note: string | null;
  lines: number;
  quantity: number;
  broken: number;
};

export type TemperingBatchCard = TemperingBatchRow & {
  items: TemperingRow[];
  /** Powierzchnia całej partii — podstawa rozksięgowania kosztu. */
  m2: number;
  skipped?: { id: number; reason: string }[];
  replaced?: number;
};

export type TemperingBatchBoard = {
  rows: TemperingBatchRow[];
  filters: { code: string; name: string; count: number }[];
  suppliers: { id: number; name: string }[];
};

export class TemperingApi extends ApiRequest {
  static prefix: string = '/tempering';

  public static async queue(
    thickness: string = '',
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get('/queue', { thickness });
  }

  public static async batches(
    status: string = 'open',
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get('/batches', { status });
  }

  public static async batch(id: number): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/batches/${id}`, {});
  }

  /** Nowa partia z zaznaczonych pozycji kolejki. */
  public static async createBatch(
    supplierId: number,
    itemIds: number[],
    expectedAt: string = '',
    note: string = '',
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post('/batches', {
      supplier_id: supplierId,
      item_ids: itemIds,
      expected_at: expectedAt,
      note,
    });
  }

  public static async sendBatch(
    id: number,
    sentAt: string = '',
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/batches/${id}/send`, { sent_at: sentAt });
  }

  /** Powrót: mapa `id pozycji => los`. Pominięta pozycja wróciła. */
  public static async receiveBatch(
    id: number,
    outcomes: Record<number, string>,
    returnedAt: string = '',
    note: string = '',
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/batches/${id}/receive`, {
      outcomes,
      returned_at: returnedAt,
      note,
    });
  }

  public static async settleBatch(
    id: number,
    netCost: string,
    document: string = '',
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/batches/${id}/settle`, {
      net_cost: netCost,
      document,
    });
  }

  public static async cancelBatch(id: number): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/batches/${id}/cancel`, {});
  }
}
