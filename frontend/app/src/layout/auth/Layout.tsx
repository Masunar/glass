import { type LoaderFunction, redirect } from 'react-router';

import { userLoader } from '@app/auth/user-loader';
import SharedLayout from '@app/layout/_shared/guest/Layout';

export const loader: LoaderFunction = async (params) => {
  const data = await userLoader(params);
  const user = data.user;

  if (user?.id) {
    return redirect('/');
  }

  // Loader musi zwrocic wartosc. undefined trafia do strumienia
  // single fetch jako nierozwiazywalna referencja - klient nie tworzy
  // wtedy routera i strona zostaje pusta, bez zadnego bledu.
  return null;
};

export default function Layout() {
  return <SharedLayout />;
}
