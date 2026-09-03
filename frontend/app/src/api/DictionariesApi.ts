import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export type DictionaryFieldType =
  'text' | 'integer' | 'decimal' | 'boolean' | 'select' | 'reference';

export type DictionaryOption = { value: string; label: string };

export type DictionaryField = {
  key: string;
  label: string;
  type: DictionaryFieldType;
  required: boolean;
  options: DictionaryOption[];
  /** Pole widoczne na liście, a nie tylko w formularzu edycji. */
  in_list: boolean;
  hint: string | null;
};

export type Dictionary = {
  slug: string;
  label: string;
  note: string | null;
  fields: DictionaryField[];
};

/** Wiersz słownika: `id` plus wartości pól, plus etykiety odwołań. */
export type DictionaryRow = {
  id: number;
} & Record<string, string | number | boolean | null>;

/**
 * Słowniki proste. Backend oddaje opis pól, więc ekran nie zna kolumn
 * żadnej z tabel — dodanie pola do słownika jest zmianą po stronie
 * definicji, nie tutaj.
 */
export class DictionariesApi extends ApiRequest {
  static prefix: string = '/dictionaries';

  public static async schema(): Promise<ResponseProps<ResponseContent>> {
    return await this.get('');
  }

  public static async rows(
    slug: string,
    includeInactive: boolean = false,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/${slug}`, {
      include_inactive: includeInactive ? 1 : 0,
    });
  }

  public static async save(
    slug: string,
    values: Record<string, unknown>,
    id?: number | null,
  ): Promise<ResponseProps<ResponseContent>> {
    return id
      ? await this.put(`/${slug}/${id}`, values)
      : await this.post(`/${slug}`, values);
  }

  /** Słownik nie usuwa, tylko dezaktywuje — historia musi się rozwiązać. */
  public static async deactivate(
    slug: string,
    id: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.delete(`/${slug}/${id}`);
  }
}
