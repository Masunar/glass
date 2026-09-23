export enum Permission {
  USERS = 'users',
  ROLES = 'roles',
  PERMISSIONS = 'permissions',
  ALERTS = 'alerts',
  PARAMETERS = 'parameters',
  PRICE_LIST = 'price_list',
  PRODUCTS = 'products',
  CONTRACTORS = 'contractors',
  ORDERS = 'orders',
  OFFERS = 'offers',
  PRODUCTION = 'production',
  WAREHOUSE = 'warehouse',
  TEMPERING = 'tempering',
  DICTIONARIES = 'dictionaries',
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
