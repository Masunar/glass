import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export type ParameterType =
  'number' | 'percent' | 'text' | 'iban' | 'template' | 'choice';

export type Parameter = {
  key: string;
  type: ParameterType;
  value: string | null;
  description: string | null;
  /** Niepusta wyłącznie dla typu 'choice'. */
  options: string[];
  changed_at: string;
  /** Inicjały autora ostatniej zmiany; null, gdy zmiana pochodzi z seedera. */
  changed_by: string | null;
};

export type ParameterBoard = {
  parameters: Parameter[];
  /** Numer ostatniego zapisu zestawu — następny będzie o jeden wyższy. */
  version: number;
};

export type ParameterImpact = {
  parameters: { key: string; percent: number | null; sample: string | null }[];
  average_percent: number | null;
};

export type ParameterHistoryEntry = {
  version: number;
  at: string;
  by: string | null;
  changes: { field: string; before: string | null; after: string | null }[];
};

export class ParametersApi extends ApiRequest {
  static prefix: string = '/parameters';

  /** Zapis obejmuje wyłącznie zmienione pola i jest niepodzielny. */
  public static async save(
    values: Record<string, string | null>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put('', { values });
  }

  /**
   * O ile wpisane, jeszcze niezapisane wartości ruszyłyby ceny.
   * Liczy serwer, bo wzór wyceny żyje po jego stronie.
   */
  public static async preview(
    values: Record<string, string | null>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post('/preview', { values });
  }

  public static async history(): Promise<ResponseProps<ResponseContent>> {
    return await this.get('/history');
  }
}
