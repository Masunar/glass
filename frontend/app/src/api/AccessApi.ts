import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export type AccessIssue = {
  kind: 'module_without_access' | 'page_without_permission' | 'permission_planned';
  module: string | null;
  label: string;
  detail: string;
};

export type RoleRow = {
  id: number;
  name: string;
  /** Omija sprawdzanie w całości — nie da się jej konfigurować. */
  is_superuser: boolean;
  permissions: number;
  packages: number;
  issues: number;
  /** Czego dotyczą — żeby nie trzeba było wchodzić, aby to sprawdzić. */
  issue_labels: string[];
};

export type AccessModule = {
  key: string;
  label: string;
  access_permission: string;
  has_access: boolean;
  /** Skąd rola ma dostęp: rola, paczka, nadane wprost. */
  access_origin: string | null;
  pages: number;
  pages_covered: number;
};

export type AccessPage = {
  code: string;
  path: string;
  module: string | null;
  label: string;
  permission: string | null;
  is_open: boolean;
  /** Strona bez uprawnienia — otwiera ją każdy zalogowany. */
  is_public: boolean;
};

export type AccessGroupItem = {
  name: string;
  sub: string;
  granted: boolean;
  origin: string | null;
};

export type AccessGroup = {
  key: string;
  label: string;
  module: string;
  page: string | null;
  /** `planned` = uprawnienie istnieje, ale żaden ekran go nie sprawdza. */
  state: 'active' | 'planned';
  note: string;
  granted: number;
  total: number;
  items: AccessGroupItem[];
};

export type AccessPackage = {
  id: number;
  name: string;
  description: string | null;
  permissions: number;
  /** Ile ról tę paczkę ma — czyli kogo dotknie jej zmiana. */
  roles: number;
  attached: boolean;
};

export type PackageRow = {
  id: number;
  name: string;
  description: string | null;
  permissions: number;
  roles: number;
  /** Nazwy ról, nie sama liczba — to jest pytanie przed zmianą paczki. */
  role_names: string[];
};

export type RolesBoard = {
  roles: RoleRow[];
  packages: PackageRow[];
  system: AccessIssue[];
};

export type PackageBoard = {
  package: {
    id: number;
    name: string;
    description: string | null;
    /** Kogo dotknie zapis — zasięg przed kliknięciem, nie po. */
    roles: string[];
  } | null;
  modules: AccessModule[];
  pages: AccessPage[];
  groups: AccessGroup[];
};

export type UserAccessBoard = {
  user: {
    id: number;
    name: string;
    email: string;
    roles: string[];
    is_superuser: boolean;
  };
  /** Nadane wprost, ponad rolę — tylko te da się tu odznaczyć. */
  direct: string[];
  modules: AccessModule[];
  pages: AccessPage[];
  groups: AccessGroup[];
};

export type RoleBoard = {
  role: { id: number; name: string; is_superuser: boolean };
  modules: AccessModule[];
  pages: AccessPage[];
  groups: AccessGroup[];
  packages: AccessPackage[];
  issues: AccessIssue[];
};

export type AccessBalance = {
  granted: number;
  added: number;
  removed: number;
  roles?: number;
  /** Pominięte, bo rola i tak je daje — nie błąd, ale nie cisza. */
  skipped?: number;
};

export class AccessApi extends ApiRequest {
  static prefix: string = '/access';

  public static async roles(): Promise<ResponseProps<ResponseContent>> {
    return await this.get('/roles');
  }

  public static async role(id: number): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/roles/${id}`);
  }

  public static async saveRole(
    id: number,
    permissions: string[],
    packages: number[],
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put(`/roles/${id}`, { permissions, packages });
  }

  public static async package(
    id: number | null,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get(id === null ? '/packages/new' : `/packages/${id}`);
  }

  public static async user(id: number): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/users/${id}`);
  }

  public static async saveUser(
    id: number,
    permissions: string[],
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put(`/users/${id}`, { permissions });
  }

  public static async savePackage(
    id: number | null,
    data: { name: string; description: string | null; permissions: string[] },
  ): Promise<ResponseProps<ResponseContent>> {
    return id === null
      ? await this.post('/packages', data)
      : await this.put(`/packages/${id}`, data);
  }

  public static async deletePackage(
    id: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.delete(`/packages/${id}`);
  }
}
