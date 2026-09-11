import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export type NextStep = {
  transition_id: number;
  to_status: string;
  to_status_code: string;
  label: string;
  available: boolean;
  /** Powód blokady — pusty, gdy przejście jest dostępne. */
  blocked_by: string | null;
  /** Warunku nie da się sprawdzić, bo brakuje modułu, nie danych. */
  unknown: boolean;
  /** Brakuje tylko powodu — da się go podać razem z akcją. */
  needs_reason: boolean;
};

export type OrderRow = {
  id: number;
  number: number;
  created_at: string | null;
  contractor: string | null;
  contractor_phone: string | null;
  note: string | null;
  status: string | null;
  status_code: string | null;
  deadline: string | null;
  /** Ujemna liczba to dni po terminie. */
  days_left: number | null;
  is_shifted: boolean;
  delivery_method: string;
  delivery_place: string | null;
  amount: string;
  owner_initials: string | null;
  is_on_hold: boolean;
  hold_reason: string | null;
  has_open_claim: boolean;
  next_step: NextStep | null;
  blocked_step: NextStep | null;
};

export type OrderBandKey = 'today' | 'overdue' | 'later';

export type OrderBoard = {
  bands: {
    key: OrderBandKey;
    count: number;
    total: string;
    rows: OrderRow[];
  }[];
  filters: { code: string | null; name: string; count: number }[];
  summary: {
    today: number;
    overdue: number;
    shown: number;
    as_of: string;
  };
};

export type OrderPane = {
  width_mm: number;
  height_mm: number;
  is_irregular_shape: boolean;
  is_tempered: boolean;
  needs_mark: boolean;
};

export type OrderCardItem = {
  id: number;
  section: string;
  name: string;
  quantity: string;
  unit_net_price: string;
  amount: string;
  processes: string[];
  pane: OrderPane | null;
};

export type OrderCardList = {
  id: number;
  number: number;
  name: string | null;
  role: 'component' | 'alternative';
  /** Wyłączona nie należy do zlecenia; wstrzymana należy, ale nie idzie dalej. */
  is_included: boolean;
  is_on_hold: boolean;
  comment: string | null;
  net: string;
  items: OrderCardItem[];
};

export type OrderPathStep = {
  code: string;
  name: string;
  is_subcontracted: boolean;
  items: number;
  amount: string;
};

export type OrderHistoryEntry = {
  at: string | null;
  event: string;
  user: string | null;
  changes: { field: string; before: unknown; after: unknown }[];
};

export type OrderCard = {
  order: {
    id: number;
    number: number;
    created_at: string | null;
    created_by: string | null;
    status: string | null;
    status_code: string | null;
    is_final: boolean;
    is_on_hold: boolean;
    hold_reason: string | null;
    has_open_claim: boolean;
    contractor: {
      id: number;
      name: string;
      display_name: string;
      tax_id: string | null;
      phone: string | null;
      email: string | null;
      address: string | null;
      city: string | null;
      contact: string | null;
    } | null;
    delivery: {
      method: string;
      place: string | null;
      address: string | null;
      contact: string | null;
    };
    invoice: {
      type: string | null;
      vat_rate: number | null;
      buyer_name: string | null;
      buyer_tax_id: string | null;
      buyer_address: string | null;
      accounting_note: string | null;
    };
    deadline: {
      client: string | null;
      production: string | null;
      shifted: string | null;
      shift_reason: string | null;
      effective: string | null;
      days_left: number | null;
    };
    comments: {
      short: string | null;
      production: string | null;
      installer: string | null;
      offer: string | null;
    };
  };
  money: OrderTotals;
  credit: {
    limit: string;
    payment_days: number;
    order_value: string;
    exceeds_by: string | null;
    is_gross: boolean;
  } | null;
  steps: NextStep[];
  path: OrderPathStep[];
  lists: OrderCardList[];
  history: OrderHistoryEntry[];
};

export type OrderFormOptions = {
  branches: { id: number; name: string }[];
  /** Nie każda lokalizacja wydaje towar — hala produkcyjna nie musi. */
  pickup_points: { id: number; name: string }[];
  invoice_types: { id: number; name: string; vat_rate: number }[];
  defaults: {
    branch_id: number | null;
    invoice_type_id: number | null;
    delivery_method: string;
  };
  status: string | null;
};

export type OrderPaneRow = {
  id: number;
  position: number;
  section: string;
  product_id: number | null;
  group: string | null;
  name: string;
  thickness_mm: number | null;
  quantity: string;
  unit_net_price: string;
  amount: string;
  /** Materiał razem z procesami — to widzi klient. */
  total: string;
  processes: {
    process_id: number;
    code: string | null;
    name: string | null;
    unit_net_price: string;
    amount: string;
  }[];
  price_path: {
    code: string;
    label: string;
    value: string;
    detail: string | null;
  }[];
  width_mm: number | null;
  height_mm: number | null;
  is_irregular_shape: boolean;
  is_tempered: boolean;
  needs_mark: boolean;
  m2: number | null;
  mb: number | null;
  kg: number | null;
};

export type OrderItemsList = {
  id: number;
  number: number;
  name: string | null;
  role: 'component' | 'alternative';
  is_included: boolean;
  is_on_hold: boolean;
  comment: string | null;
  net: string;
  glass: OrderPaneRow[];
  services: OrderPaneRow[];
};

export type OrderTotals = {
  /** Suma pozycji przed rabatem. */
  base: string;
  /** Rabat łącznie, kwotowo — procenty per sekcja się nie sumują. */
  discount: string;
  net: string;
  excluded_net: string;
  vat_rate: number | null;
  vat: string | null;
  gross: string | null;
  sections: {
    section: string;
    base: string;
    percent: string;
    discount: string;
    net: string;
  }[];
};

export type OrderDiscountRow = {
  section: string;
  percent: string;
  /** Ile wolno tej roli w sekcji cenowej kontrahenta. */
  max_percent: string;
  price_section: string | null;
};

export type OrderItemsBoard = {
  order: {
    id: number;
    number: number;
    status: string | null;
    contractor: string | null;
  };
  lists: OrderItemsList[];
  totals: OrderTotals & { m2: number; mb: number; kg: number };
  discounts: OrderDiscountRow[];
  catalogue: {
    products: {
      id: number;
      name: string;
      group: string | null;
      thickness_mm: number | null;
      is_tempered_by_default: boolean;
    }[];
    processes: {
      id: number;
      code: string;
      name: string;
      is_subcontracted: boolean;
    }[];
    services: { id: number; name: string }[];
  };
};

export type OrderDrawingRow = {
  id: number;
  name: string;
  note: string | null;
  mime: string;
  is_image: boolean;
  size_bytes: number;
  item_id: number | null;
  item_name: string | null;
  uploaded_at: string | null;
  uploaded_by: string | null;
};

export type OrderDrawingsBoard = {
  order: { id: number; number: number; status: string | null };
  drawings: OrderDrawingRow[];
  /** Komplet deklaruje człowiek — tu jest kto i kiedy. */
  complete: { declared: boolean; at: string | null; by: string | null };
  items: { id: number; name: string; list: number }[];
  accepts: string[];
  max_kilobytes: number;
};

export class OrdersApi extends ApiRequest {
  static prefix: string = '/orders';

  public static async board(
    query: string = '',
    status: string | null = null,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get('', { q: query, status: status ?? '' });
  }

  public static async formOptions(): Promise<ResponseProps<ResponseContent>> {
    return await this.get('/form');
  }

  public static async create(
    data: Record<string, unknown>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post('', data);
  }

  public static async card(
    id: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/${id}`);
  }

  /**
   * Warunki przejścia są sprawdzane ponownie po stronie serwera, więc
   * ta metoda może się nie powieść mimo widocznego przycisku.
   */
  public static async items(
    id: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/${id}/items`);
  }

  public static async savePane(
    id: number,
    data: Record<string, unknown>,
    itemId?: number | null,
  ): Promise<ResponseProps<ResponseContent>> {
    return itemId
      ? await this.put(`/${id}/panes/${itemId}`, data)
      : await this.post(`/${id}/panes`, data);
  }

  public static async saveService(
    id: number,
    data: Record<string, unknown>,
    itemId?: number | null,
  ): Promise<ResponseProps<ResponseContent>> {
    return itemId
      ? await this.put(`/${id}/services/${itemId}`, data)
      : await this.post(`/${id}/services`, data);
  }

  public static async deleteItem(
    id: number,
    itemId: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.delete(`/${id}/items/${itemId}`);
  }

  public static async saveDiscounts(
    id: number,
    discounts: Record<string, string>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put(`/${id}/discounts`, { discounts });
  }

  public static async drawings(
    id: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/${id}/drawings`);
  }

  public static async addDrawing(
    id: number,
    file: File,
    itemId: number | null,
    note: string,
  ): Promise<ResponseProps<ResponseContent>> {
    const payload = new FormData();

    payload.append('file', file);
    payload.append('note', note);

    if (itemId !== null) {
      payload.append('order_item_id', String(itemId));
    }

    return await this.post(`/${id}/drawings`, payload);
  }

  public static async deleteDrawing(
    id: number,
    drawingId: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.delete(`/${id}/drawings/${drawingId}`);
  }

  public static async declareDrawings(
    id: number,
    complete: boolean,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put(`/${id}/drawings-complete`, { complete });
  }

  /** Plik idzie przez aplikację, nie z katalogu publicznego. */
  public static drawingUrl(id: number, drawingId: number): string {
    return `${this.baseUrl ?? ''}${this.prefix}/${id}/drawings/${drawingId}`;
  }

  public static async transition(
    id: number,
    transitionId: number,
    reason?: string,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/${id}/transition`, {
      transition_id: transitionId,
      reason: reason ?? '',
    });
  }
}
