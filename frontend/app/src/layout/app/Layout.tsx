import { CssBaseline } from '@mui/material';
import { linearProgressClasses } from '@mui/material/LinearProgress';
import { authRoutes } from '@router/auth-router';
import { redirectRoutes } from '@router/redirect-router';
import { securityRoutes } from '@router/security-router';

import UserModal from '../../components/user/modal/UserModal';
import User from '../../components/user/User';
import { UserModalProvider } from '../../components/user/UserModalContext';
import { appRoutes } from '../../router/app-router';
import OfflineModal from './_components/OfflineModal';
import {
  type LoaderFunction,
  Outlet,
  matchPath,
  redirect,
  useNavigate,
} from 'react-router';
import SimpleBar from 'simplebar-react';

import { Div, Flex } from '@salvon/components/div';
import { NavigationIndicator } from '@salvon/components/progress';
import Menu from '@salvon/components/sidebar-menu/Menu';
import Sidebar from '@salvon/components/sidebar/Sidebar';
import SidebarCollapse from '@salvon/components/sidebar/SidebarCollapse';
import Topbar from '@salvon/components/topbar/Topbar';
import TopbarSizer from '@salvon/components/topbar/TopbarSizer';
import ChangeLanguage from '@salvon/components/topbar/actions/ChangeLanguage';
import Logout from '@salvon/components/topbar/actions/Logout';
import MobileMenuToggle from '@salvon/components/topbar/actions/MobileMenuToggle';
import ThemeToggle from '@salvon/components/topbar/actions/ThemeToggle';
import { useMenuControl } from '@salvon/hooks/useMenuControl';
import { useMounted } from '@salvon/hooks/useMounted';
import { useSearchParam } from '@salvon/hooks/useSearchParams';
import { useIsDarkMode, usePalette } from '@salvon/hooks/useTheme';
import { Providers } from '@salvon/provider';
import type { ApplicationRoute } from '@salvon/router';
import '@salvon/styles.css';
import generatePath from '@salvon/utils/generate-path';

import { userLoader } from '@app/auth/user-loader';
import i18n from '@app/config/i18n';
import { locales } from '@app/config/locales';
import { defaultLocale } from '@app/config/locales';
import menu from '@app/config/menu';
import { darkTheme, lightTheme } from '@app/config/theme';
import { useHasPermission, userHasPermission } from '@app/hook/use-permissions';
import LogoBadge from '@app/layout/app/_components/LogoBadge';
import GlobalSearch from '@app/layout/app/_components/global-search/GlobalSearch';
import UserProvider from '@app/provider/UserProvider';
import { stripDataSuffix } from '@app/utils/return-to';

export const loader: LoaderFunction = async (params) => {
  const data = await userLoader(params);
  const user = data.user;

  const url = new URL(params.request.url);
  // Sciezka zadania o dane ma sufiks .data - bez obciecia return_to
  // po zalogowaniu prowadzil na surowy strumien danych, nie na ekran.
  const pathname = stripDataSuffix(url.pathname).replace('/', '');
  const queryParams: any = {};

  if (pathname.replace('admin', '').length > 0) {
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
    return redirect(appRoutes.index.path ?? '/admin');
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
        darkTheme,
      }}
    >
      <UserProvider user={user}>
        <UserModalProvider
          defaultOpen={mfaRecovered}
          defaultPage={mfaRecovered ? 'mfa' : 'main'}
          mfaRecovered={mfaRecovered}
        >
          <NavigationIndicator
            height="3px"
            // sx={{
            //   [`& .${linearProgressClasses.bar}`]: {
            //     borderRadius: 5,
            //     backgroundColor: 'primary.main',
            //   },
            // }}
          >
            <Template />
          </NavigationIndicator>
        </UserModalProvider>
      </UserProvider>
    </Providers>
  );
}

function Template() {
  const { compactMode, mobileOpen } = useMenuControl();
  const renderCompactMode = compactMode && !mobileOpen;
  const palette = usePalette();
  const sidebarWidth = renderCompactMode && !mobileOpen ? 85 : 280;
  const navigate = useNavigate();
  const hasPermissionTo = useHasPermission();

  const handleLogout = () => navigate(redirectRoutes.logout.path);

  return (
    <div
      className="salvon-animate-all"
      style={{
        background: palette.salvon?.background?.default,
      }}
    >
      <CssBaseline enableColorScheme />
      <OfflineModal />
      <UserModal />
      <SidebarCollapse width={280} compactWidth={90} />
      <Sidebar width={sidebarWidth} padding={0}>
        <TopbarSizer
          height="64px"
          sx={{ px: renderCompactMode ? '10px' : '18px' }}
        >
          <LogoBadge renderCompactMode={renderCompactMode} />
        </TopbarSizer>
        <Div
          sx={{
            px: renderCompactMode ? '10px' : '12px',
            flexShrink: 0,
            mt: renderCompactMode ? '14px' : '10px',
          }}
        >
          <User compactMode={renderCompactMode} />
        </Div>
        <Div
          sx={{
            flex: '1 1 auto',
            overflow: 'hidden',
            minHeight: 0,
            px: renderCompactMode ? '10px' : '12px',
            paddingRight: '2px !important',
            paddingTop: '15px',
          }}
        >
          <Menu
            items={menu}
            compactMode={renderCompactMode}
            hasPermissionTo={hasPermissionTo}
          />
        </Div>
      </Sidebar>
      <Topbar drawerWidth={sidebarWidth} height="64px">
        <Flex justify="space-between" fw>
          <Flex gap={1}>
            <MobileMenuToggle />
            <GlobalSearch />
          </Flex>
          <Flex align="center" gap={1}>
            <ThemeToggle />
            <ChangeLanguage locales={locales} />
            <Logout logout={handleLogout} />
          </Flex>
        </Flex>
      </Topbar>
      <Div
        sx={{
          width: { md: `calc(100% - ${sidebarWidth}px)` },
          ml: { md: `${sidebarWidth}px` },
          paddingRight: '3px',
        }}
      >
        <SimpleBar
          forceVisible="y"
          autoHide={false}
          style={{
            height: 'calc(100vh - 64px)',
          }}
        >
          <Div
            sx={{
              padding: '25px',
              paddingRight: '23px',
              ...(palette.salvon?.page_container ?? {}),
              minHeight: 'calc(100vh - 64px)',
            }}
          >
            <Outlet />
          </Div>
        </SimpleBar>
      </Div>
    </div>
  );
}
