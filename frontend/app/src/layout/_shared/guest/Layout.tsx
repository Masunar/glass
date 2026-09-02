import { Grid } from '@mui/material';

import GlassScene from './_components/glass/GlassScene';
import type { ReactNode } from 'react';
import { Outlet } from 'react-router';

import { Flex } from '@salvon/components/div';
import NavigationIndicator from '@salvon/components/progress/NavigationIndicator';
import { themeMode } from '@salvon/consts/theme-mode';
import { Providers } from '@salvon/provider';

import Brand from '@app/components/layout/Brand';
import i18n from '@app/config/i18n';
import { defaultLocale } from '@app/config/locales';
import { lightTheme } from '@app/config/theme';

export default function Layout() {
  return (
    <Providers
      i18n={i18n}
      defaultLocale={defaultLocale}
      theme={{
        lightTheme,
        darkTheme: lightTheme,
        defaultMode: themeMode.light,
        forceMode: 'light',
      }}
    >
      <NavigationIndicator height="3px">
        <GuestTemplate brand={<Brand />}>
          <Flex center fw fh>
            <Flex
              center
              fw
              sx={{
                background: '#fff',
                padding: {
                  xs: '20px 20px',
                  sm: '30px 30px',
                  md: '40px 40px',
                },
                borderRadius: '12px',
                maxWidth: {
                  xs: '90%',
                  sm: '80%',
                  md: '70%',
                  ws: '70%',
                  lg: '65%',
                  xl: '59%',
                  fhd: '60%',
                  qhd: '50%',
                  uhd: '40%',
                },
                border: '1px solid #dbe1ea',
                boxShadow: '0 1px 2px rgba(15, 23, 42, 0.04)',
              }}
            >
              <Outlet />
            </Flex>
          </Flex>
        </GuestTemplate>
      </NavigationIndicator>
    </Providers>
  );
}

export function GuestTemplate({
  children,
  brand,
}: {
  children: ReactNode;
  brand?: ReactNode;
}) {
  return (
    <Grid
      container
      sx={{ margin: 0, minHeight: '100vh', padding: 1, background: '#f3f5f9' }}
    >
      <Grid
        size={{
          xs: 0,
          sm: 0,
          md: 0,
          //@ts-ignore
          ws: 5,
          lg: 6,
        }}
      >
        <GlassScene brand={brand} />
      </Grid>
      <Grid
        size={{
          xs: 12,
          sm: 12,
          md: 12,
          //@ts-ignore
          ws: 7,
          lg: 6,
        }}
      >
        <Flex column center fh fw>
          {children}
        </Flex>
      </Grid>
    </Grid>
  );
}
