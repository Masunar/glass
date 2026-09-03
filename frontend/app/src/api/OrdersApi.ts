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

export class OrdersApi extends ApiRequest {
  static prefix: string = '/orders';

  public static async board(
    query: string = '',
    status: string | null = null,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get('', { q: query, status: status ?? '' });
  }
}
