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

export type PurchaseOrderRow = {
  id: number;
  number: number;
  supplier: string;
  status: string;
  status_label: string;
  is_open: boolean;
  is_editable: boolean;
  ordered_at: string | null;
  expected_at: string | null;
  note: string | null;
  lines: number;
  quantity_ordered: number;
  quantity_received: number;
  /** `null` znaczy „nieznana", nie „zero" — choć jedna pozycja bez ceny. */
  net_value: string | null;
};

export type PurchaseOrderItemRow = {
  id: number;
  product_id: number;
  code: string | null;
  name: string;
  ordered: number;
  received: number;
  outstanding: number;
  unit_net_price: string | null;
};

export type PurchaseReceiptRow = {
  id: number;
  received_at: string;
  document: string | null;
  note: string | null;
  lines: number;
};

export type PurchaseOrderCard = PurchaseOrderRow & {
  items: PurchaseOrderItemRow[];
  receipts: PurchaseReceiptRow[];
};

export type PurchaseOrderBoard = {
  rows: PurchaseOrderRow[];
  filters: { code: string; name: string; count: number }[];
  suppliers: { id: number; name: string }[];
};

/** Pozycja pominięta przy tworzeniu zamówień z sugestii, z powodem. */
export type SkippedSuggestion = {
  product_id: number;
  name: string;
  reason: string;
};

export type DriftRow = {
  product_id: number;
  code: string | null;
  name: string;
  /** Stara i nowa obok siebie — samo „zmieniło się" nie wystarcza. */
  previous_purchase_price: string | null;
  purchase_price: string;
  changed_at: string;
  priced_at: string;
  coefficient: string;
  list_net_price: string | null;
};

export type DriftBoard = {
  rows: DriftRow[];
  summary: { drifted: number };
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
  /** Zamówienia do dostawców. `status`: kod, `open` albo `all`. */
  public static async orders(
    status: string = 'open',
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get('/orders', { status });
  }

  public static async order(id: number): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/orders/${id}`, {});
  }

  public static async createOrder(
    supplierId: number,
    expectedAt: string = '',
    note: string = '',
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post('/orders', {
      supplier_id: supplierId,
      expected_at: expectedAt,
      note,
    });
  }

  /** Szkice z zaznaczonych sugestii; odpowiedź niesie też pominięte. */
  public static async ordersFromSuggestions(
    productIds: number[],
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post('/orders/from-suggestions', { product_ids: productIds });
  }

  public static async addOrderItem(
    orderId: number,
    productId: number,
    quantity: number,
    unitNetPrice: string = '',
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/orders/${orderId}/items`, {
      product_id: productId,
      quantity,
      unit_net_price: unitNetPrice,
    });
  }

  public static async removeOrderItem(
    orderId: number,
    itemId: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.delete(`/orders/${orderId}/items/${itemId}`, {});
  }

  public static async sendOrder(id: number): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/orders/${id}/send`, {});
  }

  public static async cancelOrder(id: number): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/orders/${id}/cancel`, {});
  }

  /** Przyjęcie towaru: podnosi stan i zapisuje nową cenę zakupu. */
  public static async receiveOrder(
    id: number,
    lines: { item_id: number; quantity: number; unit_net_price: string }[],
    receivedAt: string = '',
    document: string = '',
    note: string = '',
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/orders/${id}/receive`, {
      lines,
      received_at: receivedAt,
      document,
      note,
    });
  }

  /** Produkty, których cena zakupu wyprzedziła cennik sprzedaży. */
  public static async priceDrift(): Promise<ResponseProps<ResponseContent>> {
    return await this.get('/price-drift', {});
  }
}
