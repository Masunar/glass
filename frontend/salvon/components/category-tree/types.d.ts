import type { CSSProperties, ReactNode } from 'react';

export type CategoryNode = {
  id: string;
  name: string;
  is_active?: boolean;
  children?: CategoryNode[];
};

export type CategoryTreeTranslations = {
  search?: string;
  noName?: string;
};

type BaseCategoryTreeProps = {
  data: CategoryNode[];
  actions?: (node: CategoryNode) => ReactNode;
  onChange?: (data: CategoryNode[]) => void;
  translations?: CategoryTreeTranslations;
};

/** Auto-height on: fill the viewport minus `autoHeightOffset` px. `height` is forbidden. */
type AutoHeightProps = {
  autoHeight: true;
  autoHeightOffset?: number;
  height?: never;
};

/** Auto-height off: size via `height` (defaults to filling the container). */
type FixedHeightProps = {
  autoHeight?: false;
  autoHeightOffset?: never;
  height?: CSSProperties['height'];
};

export type CategoryTreeProps = BaseCategoryTreeProps &
  (AutoHeightProps | FixedHeightProps);
