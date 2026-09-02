export enum Permission {
  USERS = 'users',
  ROLES = 'roles',
  PARAMETERS = 'parameters',
  PRICE_LIST = 'price_list',
}

export enum SubPermission {
  WILDCARD = '*',
  LIST = 'list',
  CREATE = 'create',
  READ = 'read',
  UPDATE = 'update',
  DELETE = 'delete',
  RESTORE = 'restore',
  EXPORT = 'export',
  IMPORT = 'import',
  MANAGE = 'manage',
}
