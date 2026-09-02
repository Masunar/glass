type TranslateFn = (key: string) => string;

export function translatePermissionEntity(
  t: TranslateFn,
  entity: string,
): string {
  return t(`page.admin.permissions.entities.${entity}`) || entity;
}

export function translatePermissionAction(
  t: TranslateFn,
  action: string,
): string {
  return t(`page.admin.permissions.actions.${action}`) || action;
}

export function translatePermission(
  t: TranslateFn,
  permission: string,
  subPermission?: string,
): string {
  const entity = translatePermissionEntity(t, permission);
  return subPermission
    ? `${entity} - ${translatePermissionAction(t, subPermission)}`
    : entity;
}

export function permissionToOption(t: TranslateFn, permissionName: string) {
  const [entity, action] = permissionName.split('.');
  return {
    label: `${translatePermissionEntity(t, entity)} - ${translatePermissionAction(t, action)}`,
    value: permissionName,
    group: translatePermissionEntity(t, entity),
  };
}
