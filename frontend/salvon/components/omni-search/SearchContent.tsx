import { LinearProgress, alpha } from '@mui/material';

import Footer from './components/Footer';
import Input from './components/Input';
import Results from './components/Results';
import type {
  OmniSearchFooterLabels,
  OmniSearchGroupType,
  OmniSearchItemType,
} from './types.d';
import { useSearch } from './useSearch';
import type { ReactNode } from 'react';

import { Div } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';

export type SearchContentProps = {
  noResultsItem: ReactNode;
  setOpen: (v: boolean) => void;
  searchIcon?: ReactNode;
  loading?: boolean;
  searchPlaceholder?: string;
  items?: OmniSearchItemType[];
  groups?: OmniSearchGroupType[];
  showFooter?: boolean;
  footerLabels?: OmniSearchFooterLabels;
  limit?: number;
};

export default function SearchContent({
  searchIcon,
  setOpen,
  loading,
  noResultsItem,
  searchPlaceholder,
  limit,
  items = [],
  groups,
  showFooter = true,
  footerLabels,
}: SearchContentProps) {
  //@ts-ignore
  const accent = usePalette().primary?.main ?? '#a1a1a1';
  const {
    query,
    setQuery,
    active,
    setActive,
    rendered,
    flat,
    select,
    rowRef,
    onKeyDown,
  } = useSearch({ items, groups, limit, onClose: () => setOpen(false) });

  return (
    <Div>
      <Input
        value={query}
        onChange={setQuery}
        onKeyDown={onKeyDown}
        onClose={() => setOpen(false)}
        icon={searchIcon}
        placeholder={searchPlaceholder}
      />

      {loading && (
        <LinearProgress
          sx={{
            height: '2px',
            background: alpha(accent, 0.3),
            '& .MuiLinearProgress-bar': { background: accent },
          }}
        />
      )}

      <Results
        groups={rendered}
        total={flat.length}
        activeIndex={active}
        noResultsItem={noResultsItem}
        onSelect={select}
        onHover={setActive}
        rowRef={rowRef}
      />

      {showFooter && <Footer labels={footerLabels} count={flat.length} />}
    </Div>
  );
}
