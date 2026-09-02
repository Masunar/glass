import { type LoaderFunctionArgs } from 'react-router';

import { isMfaRequired } from '@salvon/utils/api';

import { AuthApi } from '@app/api/AuthApi';
import type { User } from '@app/auth/user';

export type UserLoaderReturnType = {
  user?: User;
  mfa_required: boolean;
};

export const userLoader = async ({
  request,
}: LoaderFunctionArgs): Promise<UserLoaderReturnType> => {
  const Cookie = request.headers.get('Cookie');
  const protocol = request.headers.get('x-forwarded-proto') || 'http';
  const url = new URL(request.url);
  const Origin = `${protocol}://${url.host}`;

  const { content } = await AuthApi.user({
    headers: {
      Cookie,
      Origin,
    },
  });

  return { user: content?.data, mfa_required: isMfaRequired(content) };
};
