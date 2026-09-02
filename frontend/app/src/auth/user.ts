export type Permission = {
  id: number;
  name: string;
  guard_name?: string;
};

export type User = {
  id: number;
  email: string;
  name: string;
  first_name: string;
  last_name?: string;
  phone?: string;
  title?: string;
  country_code?: string;
  last_login_at?: string;
  type?: 'admin' | 'client';
  is_super_user: boolean;
  permissions: Permission[];
  mfa_type?: 'totp' | 'email' | null;
  mfa_active?: boolean;
  roles?: string[];
};
