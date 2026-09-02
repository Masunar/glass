import { useIsHydrated } from '../../hooks/useIsHydrated';
import type { PageEditorProps } from './types.d';
import { type JSX, Suspense, lazy } from 'react';

import { Div } from '@salvon/components/div';

const DefaultEditor = lazy(() => import('././DefaultEditor'));

export default function PageEditor(props: PageEditorProps) {
  const hydrated = useIsHydrated();

  const fallback = (
    <Div className="puck-editor-light" sx={{ height: props.height ?? 600 }} />
  );

  if (!hydrated) {
    return fallback;
  }

  return (
    <Suspense fallback={fallback}>
      <DefaultEditor {...props} />
    </Suspense>
  );
}
