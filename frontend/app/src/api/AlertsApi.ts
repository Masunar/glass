import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

/** Znacznik alertu w wierszu listy. */
export type AlertMark = {
  code: string;
  label: string;
  /** Pusty kolor znaczy „domyślny kolor alertu", nie brak koloru. */
  color: string | null;
  category: string;
  /** Wartość, która alert wywołała — bieżąca, nie ta z chwili otwarcia. */
  value: string | null;
  /** Od kiedy alert jest otwarty. */
  since: string | null;
  /** Wystąpienie w bazie — bez niego nie ma czego odhaczyć. */
  occurrence_id: number | null;
  /** Odhaczony milknie w licznikach, ale zostaje w wierszu. */
  acknowledged: boolean;
  acknowledged_at: string | null;
  acknowledged_by: string | null;
};

export type AlertParameter = {
  key: string;
  label: string;
  type: 'days' | 'statuses' | string;
  default: number | string | string[];
  hint: string;
};

export type AlertConditionDefinition = {
  type: string;
  label: string;
  category: string;
  module: string;
  parameters: AlertParameter[];
};

export type AlertRuleRow = {
  id: number;
  code: string;
  name: string;
  module: string;
  category: string;
  type: string;
  /** `null` znaczy, że katalog nie zna już tego typu — reguła jest zepsuta. */
  type_label: string | null;
  params: Record<string, unknown>;
  label: string;
  color: string | null;
  position: number;
  is_active: boolean;
  /** `null` przy regule wyłączonej: nic jej nie przelicza. */
  open: number | null;
};

export type AlertBoard = {
  rules: AlertRuleRow[];
  catalog: AlertConditionDefinition[];
  statuses: { code: string; name: string }[];
  modules: { key: string; name: string }[];
  categories: { value: string; name: string }[];
};

export type AlertRulePayload = {
  code: string;
  name: string;
  label: string;
  type: string;
  module?: string;
  color?: string | null;
  position?: number;
  is_active?: boolean;
  params?: Record<string, unknown>;
};

export class AlertsApi extends ApiRequest {
  static prefix: string = '/alerts';

  public static async board(): Promise<ResponseProps<ResponseContent>> {
    return await this.get('');
  }

  public static async create(
    payload: AlertRulePayload,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post('', payload);
  }

  public static async update(
    id: number,
    payload: AlertRulePayload,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put(`/${id}`, payload);
  }

  public static async remove(
    id: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.delete(`/${id}`);
  }
}

/**
 * Odhaczanie alertów — osobny adres, bo osobne uprawnienie.
 *
 * `/alerts` to konfiguracja reguł i chodzi na `alerts`; odhaczenie jest
 * decyzją o zleceniu i chodzi na `orders.update`.
 */
export class AlertOccurrencesApi extends ApiRequest {
  static prefix: string = '/alert-occurrences';

  public static async acknowledge(
    id: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/${id}/acknowledge`, {});
  }

  public static async revoke(
    id: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.delete(`/${id}/acknowledge`);
  }
}
