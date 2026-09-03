import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export type ContractorRow = {
  id: number;
  type: 'person' | 'company';
  name: string;
  short_name: string | null;
  display_name: string;
  tax_id: string | null;
  phone: string | null;
  email: string | null;
  payment_days: number;
  credit_limit: string;
  is_active: boolean;
};

export type ContractorAddress = {
  country: string;
  voivodeship: string | null;
  county: string | null;
  post_office: string | null;
  city: string | null;
  postal_code: string | null;
  street: string | null;
  building_number: string | null;
  unit_number: string | null;
  one_line: string;
};

export type ContractorPriceSection = {
  section: string;
  price_section_id: number | null;
  price_section_name: string | null;
  /** Nazwa sekcji obowiązującej, gdy nic nie przypisano. */
  default_name: string | null;
};

export type ContractorCard = {
  contractor: ContractorRow & {
    registry_id: string | null;
    first_name: string | null;
    last_name: string | null;
    website: string | null;
    note: string | null;
    is_supplier: boolean;
    registered_on: string | null;
  };
  addresses: Record<string, ContractorAddress | null>;
  contacts: {
    id: number;
    first_name: string | null;
    last_name: string | null;
    position: string | null;
    phone: string | null;
    email: string | null;
    is_primary: boolean;
  }[];
  price_sections: ContractorPriceSection[];
};

export class ContractorsApi extends ApiRequest {
  static prefix: string = '/contractors';

  public static async search(
    query: string,
    includeInactive: boolean = false,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get('', {
      query,
      include_inactive: includeInactive ? 1 : 0,
    });
  }

  public static async card(
    id: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/${id}`);
  }

  public static async save(
    data: Record<string, unknown>,
    id?: number | null,
  ): Promise<ResponseProps<ResponseContent>> {
    return id ? await this.put(`/${id}`, data) : await this.post('', data);
  }

  public static async savePriceSections(
    id: number,
    sections: Record<string, number | null>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put(`/${id}/price-sections`, { sections });
  }
}
