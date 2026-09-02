import type { User } from '@app/auth/user';

type TFunction = (key: string) => string;

export const userName = (
  user: User,
  withTitle: boolean = false,
  t?: TFunction,
): string => {
  if (!user) {
    return '';
  }

  const name = user.last_name
    ? `${user.first_name} ${user.last_name}`
    : user.first_name;

  if (withTitle && user.title) {
    const title = t ? t(`page.admin.clients.${user.title}`) : user.title;
    return `${title} ${name}`;
  }

  return name;
};
