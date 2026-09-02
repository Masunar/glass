import ErrorBoundaryHandler from '@root/error/ErrorBoundaryHandler';
import RootLayout from '@root/root-layout';

import type { Route } from '@router-types/app/+types/root';

import type { ReactNode } from 'react';
import { Outlet } from 'react-router';

import '@salvon/globals';

export const links: Route.LinksFunction = () => [];

export function Layout({ children }: { children: ReactNode }) {
  return <RootLayout>{children}</RootLayout>;
}

export default function App() {
  return <Outlet />;
}

export function ErrorBoundary(props: Route.ErrorBoundaryProps) {
  return <ErrorBoundaryHandler {...props} />;
}
