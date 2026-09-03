import { CssBaseline } from '@mui/material';
import { authRoutes } from '@router/auth-router';
import { redirectRoutes } from '@router/redirect-router';
import { securityRoutes } from '@router/security-router';

import { UserModalProvider } from '../../components/user/UserModalContext';
import UserModal from '../../components/user/modal/UserModal';
import { appRoutes } from '../../router/app-router';
import OfflineModal from './_components/OfflineModal';
import {
  type LoaderFunction,
  Outlet,
  matchPath,
  redirect,
  useLocation,
  useNavigate,
} from 'react-router';
import SimpleBar from 'simplebar-react';

import { Div, Flex } from '@salvon/components/div';
import { NavigationIndicator } from '@salvon/components/progress';
import ChangeLanguage from '@salvon/components/topbar/actions/ChangeLanguage';
import Logout from '@salvon/components/topbar/actions/Logout';
import { themeMode } from '@salvon/consts/theme-mode';
import { useMounted } from '@salvon/hooks/useMounted';
import { useSearchParam } from '@salvon/hooks/useSearchParams';
import { Providers } from '@salvon/provider';
import type { ApplicationRoute } from '@salvon/router';
import '@salvon/styles.css';
import generatePath from '@salvon/utils/generate-path';

import { userLoader } from '@app/auth/user-loader';
import i18n from '@app/config/i18n';
import { locales } from '@app/config/locales';
import { defaultLocale } from '@app/config/locales';
import { moduleForPath } from '@app/config/modules';
import { lightTheme } from '@app/config/theme';
import { userHasPermission } from '@app/hook/use-permissions';
import { useUser } from '@app/hook/use-user';
import GlobalSearch from '@app/layout/app/_components/global-search/GlobalSearch';
import ModulePanel from '@app/layout/app/_components/shell/ModulePanel';
import Rail from '@app/layout/app/_components/shell/Rail';
import UserProvider from '@app/provider/UserProvider';
import '@app/styles/list.css';
import '@app/styles/shell.css';
import { stripDataSuffix } from '@app/utils/return-to';

export const loader: LoaderFunction = async (params) => {
  const data = await userLoader(params);
  const user = data.user;

  const url = new URL(params.request.url);
  // Sciezka zadania o dane ma sufiks .data - bez obciecia return_to
  // po zalogowaniu prowadzil na surowy strumien danych, nie na ekran.
  const pathname = stripDataSuffix(url.pathname).replace('/', '');
  const queryParams: any = {};

  if (pathname.length > 0) {
    queryParams.return_to = `/${pathname}`;
  }

  if (data.mfa_required) {
    return redirect(
      generatePath(securityRoutes.mfa, {
        query: queryParams,
      }),
    );
  }

  if (!user?.id) {
    return redirect(
      generatePath(authRoutes.login, {
        query: queryParams,
      }),
    );
  }

  const matched = (Object.values(appRoutes) as ApplicationRoute[]).find(
    (route) => route.path && matchPath(route.path, url.pathname),
  );

  if (
    matched?.permissions?.length &&
    !userHasPermission(user, matched.permissions)
  ) {
    // Przekierowanie na siebie samego to petla, a nie zabezpieczenie.
    if (url.pathname === appRoutes.index.path) {
      return { user };
    }

    return redirect(appRoutes.index.path);
  }

  return { user };
};

export default function Layout({ loaderData }: any) {
  const user = loaderData.user;
  const mfaRecovered = !!useSearchParam('mfa_recovered');

  useMounted(() => {
    if (!mfaRecovered) {
      return;
    }

    const url = new URL(window.location.href);
    url.searchParams.delete('mfa_recovered');
    window.history.replaceState({}, '', url.pathname + url.search);
  });

  return (
    <Providers
      i18n={i18n}
      defaultLocale={defaultLocale}
      theme={{
        lightTheme,
        // Tryb ciemny wylaczony: tokeny Industry maja wylacznie palete
        // jasna, a wlasna rampa ciemna bylaby zgadywaniem. Przypisanie
        // jasnego motywu tutaj sprawia, ze zapisany wczesniej wybor
        // "ciemny" tez renderuje sie poprawnie. Wlaczenie z powrotem to
        // podmiana tej jednej linii.
        darkTheme: lightTheme,
        defaultMode: themeMode.light,
      }}
    >
      <UserProvider user={user}>
        <UserModalProvider
          defaultOpen={mfaRecovered}
          defaultPage={mfaRecovered ? 'mfa' : 'main'}
          mfaRecovered={mfaRecovered}
        >
          <NavigationIndicator height="3px">
            <Template />
          </NavigationIndicator>
        </UserModalProvider>
      </UserProvider>
    </Providers>
  );
}

function Template() {
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const user = useUser();
  const module = moduleForPath(pathname);

  const handleLogout = () => navigate(redirectRoutes.logout.path);

  const initials = [user?.first_name, user?.last_name]
    .filter(Boolean)
    .map((part) => String(part).charAt(0).toUpperCase())
    .join('')
    .slice(0, 2);

  return (
    <div
      className="ge-shell"
      style={{
        ['--ge-mod' as string]: `var(--m-${module.key})`,
        ['--ge-mod-tint' as string]: `var(--m-${module.key}-tint)`,
      }}
    >
      <CssBaseline enableColorScheme />
      <OfflineModal />
      <UserModal />

      <Rail
        active={module}
        initials={initials || 'GE'}
        onUserClick={() => {}}
      />

      <ModulePanel
        module={module}
        footer={
          <Flex align="center" justify="space-between" gap={1}>
            <ChangeLanguage locales={locales} />
            <Logout logout={handleLogout} />
          </Flex>
        }
      >
        <Div sx={{ px: '20px', py: '10px' }}>
          <GlobalSearch />
        </Div>
      </ModulePanel>

      <div className="ge-shell__content">
        <SimpleBar
          forceVisible="y"
          autoHide={false}
          style={{ height: '100vh' }}
        >
          <div className="ge-page">
            <Outlet />
          </div>
        </SimpleBar>
      </div>
    </div>
  );
}
