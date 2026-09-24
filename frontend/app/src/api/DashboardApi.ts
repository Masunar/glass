import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export type TaskBand = 'overdue' | 'today' | 'later';

export type DashboardTask = {
  id: number;
  number: number;
  contractor: string | null;
  status: string | null;
  days_left: number | null;
  /** Termin słowem: „dziś", „jutro", „5 dni po". Liczy serwer. */
  deadline_label: string | null;
  band: TaskBand;
  /** Prowadzący = zalogowany. Moje sprawy idą na górę pasma. */
  is_mine: boolean;
  owner: string | null;
  owner_initials: string | null;
  next_step: {
    transition_id: number;
    label: string;
    to_status: string;
  } | null;
};

/** Pasmo alertów: reguła, ile razy zapalona i pierwsze zlecenia. */
export type DashboardAlert = {
  code: string;
  name: string;
  label: string;
  color: string | null;
  category: string;
  module: string;
  /** Zasób, którego alert dotyczy: `orders`, `warehouse`, `tempering`. */
  resource: string;
  count: number;
  /** Nie „zlecenia": reguła może dotyczyć produktu albo partii w piecu. */
  subjects: {
    label: string;
    path: string | null;
    value: string | null;
    /** Prowadzący zlecenia. `null` przy rzeczy, która nie ma właściciela. */
    owner: string | null;
    owner_initials: string | null;
    /** Moje sprawy przychodzą pierwsze — kolejność ustala serwer. */
    is_mine: boolean;
  }[];
};

export type DashboardBoard = {
  as_of: string;
  user: { name: string; location: string | null };
  summary: { tasks: number; overdue: number; today: number; later: number };
  /** Pierwsza sprawa — propozycja startu, nie kolejny licznik. */
  top: DashboardTask | null;
  /** `null` przy liczniku znaczy brak dostępu, nie zero. */
  counters: {
    overdue: number | null;
    today: number | null;
    shortages: number | null;
    production: number | null;
    furnace: number | null;
    offers: number | null;
  };
  alerts: DashboardAlert[];
  tasks: DashboardTask[];
  blocked: { reason: string; count: number }[];
  shortages: {
    total: number;
    rows: {
      product_id: number;
      name: string;
      available: number;
      max: number;
    }[];
  } | null;
};

export class DashboardApi extends ApiRequest {
  static prefix: string = '/dashboard';

  public static async board(): Promise<ResponseProps<ResponseContent>> {
    return await this.get('');
  }
}
