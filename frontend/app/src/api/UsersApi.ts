import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export type UserRow = {
  id: number;
  name: string;
  initials: string;
  email: string;
  phone: string | null;
  location: string | null;
  roles: string[];
  /** Identyfikatory ról — formularz edycji musi wiedzieć, co zaznaczyć. */
  role_ids: number[];
  /** Rola nadrzędna omija sprawdzanie uprawnień — widok mówi to wprost. */
  is_superuser: boolean;
  is_active: boolean;
  is_self: boolean;
  last_login_at: string | null;
  logged_in_today: boolean;
  is_stale: boolean;
  /** Ile dni czeka zaproszenie; null, jeśli konto już działa. */
  waiting_days: number | null;
  created_at: string | null;
};

export type UserGroupKey = 'active' | 'invited' | 'disabled';

export type UserBoard = {
  summary: {
    active: number;
    logged_in_today: number;
    invited: number;
    oldest_invite_days: number | null;
    disabled: number;
    roles: number;
    role_names: string[];
    stale: number;
    stale_days: number;
    total: number;
    as_of: string;
  };
  groups: { key: UserGroupKey; rows: UserRow[] }[];
};

export class UsersApi extends ApiRequest {
  static prefix: string = '/users';

  public static async roles(
    data: any = {},
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get('/roles', data);
  }

  /** Lista w pasmach z podsumowaniem — jeden przelot zamiast czterech. */
  public static async board(): Promise<ResponseProps<ResponseContent>> {
    return await this.get('/board');
  }

  /**
   * Zaproszenie to link do ustawienia hasła. Zakładanie konta nadaje
   * losowe hasło, którego nikt nie zna, więc bez tego kroku nowy
   * użytkownik nie ma jak wejść do systemu.
   */
  public static async invite(
    id: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/${id}/invite`, {});
  }
}
