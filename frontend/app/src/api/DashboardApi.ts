import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export type DashboardOrderRow = {
  id: number;
  number: number;
  contractor: string | null;
  status: string | null;
  days_left: number | null;
  owner_initials: string | null;
  owner_id: number | null;
  next_step: { label: string; to_status: string } | null;
};

export type DashboardOfferRow = {
  id: number;
  number: string;
  order_id: number;
  contractor: string | null;
  status_label: string;
  issued_at: string;
  is_expired: boolean;
};

export type DashboardBoard = {
  as_of: string;
  /** Znika w całości, gdy nic w systemie nie jest adresowane do osoby. */
  mine: {
    orders: DashboardOrderRow[];
    orders_total: number;
    offers: DashboardOfferRow[];
    offers_total: number;
  } | null;
  orders: {
    today: number;
    overdue: number;
    ready: DashboardOrderRow[];
    ready_total: number;
    /** Zablokowane liczone po powodzie, nie po zleceniu. */
    blocked: { reason: string; count: number }[];
  } | null;
  production: {
    queue: {
      waiting: number;
      problems: number;
      overdue: number;
      urgent: number;
    } | null;
    furnace: { waiting: number; kg: number } | null;
  } | null;
  warehouse: {
    shortages: number;
    rows: {
      product_id: number;
      name: string;
      available: number;
      /** Ile domówić, żeby wrócić do maksimum — sugestia, nie zamówienie. */
      to_order: number;
    }[];
  } | null;
  offers: { open: number; rows: DashboardOfferRow[] } | null;
};

export class DashboardApi extends ApiRequest {
  static prefix: string = '/dashboard';

  public static async board(): Promise<ResponseProps<ResponseContent>> {
    return await this.get('');
  }
}
