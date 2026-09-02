import { Button } from '@mui/material';

import type { Route } from '@router-types/app/+types/root';

import { GuestTemplate } from '../src/layout/_shared/guest/Layout';
import { isRouteErrorResponse } from 'react-router';

import { Div, Flex } from '@salvon/components/div';
import { themeMode } from '@salvon/consts/theme-mode';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { Providers } from '@salvon/provider';

import Brand from '@app/components/layout/Brand';
import i18n from '@app/config/i18n';
import { defaultLocale } from '@app/config/locales';
import { darkTheme, lightTheme } from '@app/config/theme';
import Sheep from '@app/layout/_shared/guest/_components/Sheep';

type Props = Route.ErrorBoundaryProps;

export default function ErrorBoundaryHandler({ error }: Props) {
  return (
    <Providers
      i18n={i18n}
      defaultLocale={defaultLocale}
      theme={{
        lightTheme,
        darkTheme,
        defaultMode: themeMode.light,
        forceMode: 'light',
      }}
    >
      <ErrorContent error={error} />
    </Providers>
  );
}

function ErrorContent({ error }: any) {
  const t = useTranslation();

  let code = '500';
  let message = t('page.error.title_generic');
  let details = t('page.error.details_generic');
  let stack: string | undefined;

  if (isRouteErrorResponse(error)) {
    code = String(error.status);
    message =
      error.status === 404
        ? t('page.error.title_404')
        : t('page.error.title_generic');
    details =
      error.status === 404
        ? t('page.error.details_404')
        : error.statusText || details;
  } else if (import.meta.env.DEV && error && error instanceof Error) {
    details = error.message;
    stack = error.stack;
  }

  return (
    <GuestTemplate brand={<Brand />}>
      <Flex column center fh fw sx={{ padding: '24px' }}>
        <Flex sx={{ gap: 5 }}>
          <Sheep />
          <>
            {code && (
              <Div
                sx={{
                  fontSize: '54px',
                  fontWeight: 500,
                  lineHeight: 1,
                  color: '#575757',
                  marginBottom: '40px',
                  alignContent: 'end',
                }}
              >
                {code}
              </Div>
            )}
          </>
        </Flex>

        <Div
          sx={{
            fontSize: { xs: '24px', md: '32px' },
            fontWeight: 600,
            color: '#222222',
            marginBottom: '12px',
            textAlign: 'center',
          }}
        >
          {message}
        </Div>

        <Div
          sx={{
            fontSize: '16px',
            color: '#5c6b6a',
            maxWidth: '480px',
            marginBottom: '32px',
            textAlign: 'center',
          }}
        >
          {details}
        </Div>

        <Button
          component="a"
          href="/"
          variant="contained"
          sx={{
            textTransform: 'none',
            borderRadius: '8px',
            px: 3,
          }}
        >
          {t('page.error.go_home')}
        </Button>

        {stack && (
          <Div
            sx={{
              marginTop: '40px',
              width: '90%',
              background: '#0f172a',
              color: '#e2e8f0',
              borderRadius: '12px',
              padding: '20px',
              textAlign: 'left',
              overflow: 'auto',
              fontSize: '13px',
              fontFamily: 'monospace',
            }}
          >
            <pre style={{ margin: 0, whiteSpace: 'pre-wrap' }}>
              <code>{stack}</code>
            </pre>
          </Div>
        )}
      </Flex>
    </GuestTemplate>
  );
}
