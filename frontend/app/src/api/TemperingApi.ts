import { ApiRequest } from './ApiRequest';
import type { PaneShape } from './OrdersApi';

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
  shape: PaneShape;
  /** Znak z formatki. Co fizycznie oznacza — H-01, nadal otwarte. */
  needs_mark: boolean;
  note: string | null;
  /** Termin zlecenia — bez niego dobieranie wsadu to zgadywanie. */
  deadline: string | null;
  is_urgent: boolean;
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
  vehicle: string | null;
  vehicle_id: number | null;
  /** Dopuszczalna masa ładunku. `null`, gdy auto nie jest wskazane. */
  payload_kg: number | null;
  load_kg: number;
  /** `null` bez auta — sto procent z niczego byłoby liczbą wymyśloną. */
  load_percent: number | null;
  /** O ile za dużo. Ostrzega, nie blokuje. */
  over_by_kg: number | null;
  /** Planowany wyjazd, osobno od `sent_at`, który jest faktem. */
  departure_at: string | null;
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
  vehicles: { id: number; name: string; payload_kg: number }[];
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

  public static async batch(
    id: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/batches/${id}`, {});
  }

  /** Nowa partia z zaznaczonych pozycji kolejki. */
  public static async createBatch(
    supplierId: number,
    itemIds: number[],
    expectedAt: string = '',
    note: string = '',
    vehicleId: string = '',
    departureAt: string = '',
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post('/batches', {
      supplier_id: supplierId,
      item_ids: itemIds,
      expected_at: expectedAt,
      note,
      vehicle_id: vehicleId,
      departure_at: departureAt,
    });
  }

  /** Zmiana planu kursu — wolno do wyjazdu, bo partia jest planem. */
  public static async planBatch(
    id: number,
    vehicleId: string,
    departureAt: string,
    expectedAt: string,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put(`/batches/${id}/plan`, {
      vehicle_id: vehicleId,
      departure_at: departureAt,
      expected_at: expectedAt,
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

  public static async cancelBatch(
    id: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/batches/${id}/cancel`, {});
  }
}
