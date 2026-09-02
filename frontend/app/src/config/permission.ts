export enum Permission {
  USERS = 'users',
  ROLES = 'roles',
  PARAMETERS = 'parameters',
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
