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
  money: {
    net: string;
    vat_rate: number | null;
    /** Puste, dopóki zlecenie nie ma typu faktury — stawki nie zgadujemy. */
    vat: string | null;
    gross: string | null;
    excluded_net: string;
  };
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

export class OrdersApi extends ApiRequest {
  static prefix: string = '/orders';

  public static async board(
    query: string = '',
    status: string | null = null,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get('', { q: query, status: status ?? '' });
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
