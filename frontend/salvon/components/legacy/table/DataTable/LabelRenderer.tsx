import { type FC, type ReactNode, createElement, isValidElement } from 'react';

import { useTranslation } from '@salvon/hooks/useTranslation';

interface Props {
  label: string | ReactNode | FC;
}

export default function LabelRenderer({ label }: Props) {
  const t = useTranslation();

  if (typeof label === 'string') {
    return <span>{t(label)}</span>;
  }

  if (isValidElement(label)) {
    return label;
  }

  if (typeof label === 'function') {
    return createElement(label);
  }

  return 'Invalid label element.';
}
