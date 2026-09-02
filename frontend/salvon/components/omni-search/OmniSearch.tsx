import { Dialog, type DialogProps } from '@mui/material';

import SearchContent from './SearchContent';
import type {
  OmniSearchFooterLabels,
  OmniSearchGroupType,
  OmniSearchItemType,
} from './types.d';
import { type ReactNode, useEffect, useState } from 'react';

import { usePalette } from '@salvon/hooks/useTheme';
import type { ControllableOptionalState, SlotItem } from '@salvon/types';

export type OmniSearchProps = {
  noResultsItem: ReactNode;
  /** Flat list of items. Ignored when `groups` is provided. */
  items?: OmniSearchItemType[];
  /** Grouped items, rendered under uppercase section headers. */
  groups?: OmniSearchGroupType[];
  searchPlaceholder?: string;
  searchIcon?: ReactNode;
  loading?: boolean;
  limit?: number;
  showFooter?: boolean;
  footerLabels?: OmniSearchFooterLabels;
  slotProps?: {
    dialog?: SlotItem<DialogProps>;
  };
} & ControllableOptionalState;

export default function OmniSearch({
  searchIcon,
  items,
  groups,
  noResultsItem,
  searchPlaceholder,
  limit,
  open,
  setOpen,
  slotProps,
  showFooter = true,
  footerLabels,
  loading = false,
}: OmniSearchProps) {
  const palette = usePalette();
  const frameColor = palette.salvon?.omni_search?.frame ?? '#a1a1a1';
  const { dialog } = slotProps ?? {};
  const [internalControlledOpen, setInternalControlledOpen] = useState(false);

  const openState = open ?? internalControlledOpen;
  const setOpenState = setOpen ?? setInternalControlledOpen;

  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.metaKey && e.key.toLowerCase() === 'k') {
        setOpenState(true);
        e.preventDefault();
      }
    };

    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, []);

  return (
    <Dialog
      {...dialog}
      slotProps={{
        ...(dialog?.slotProps ?? {}),
        paper: {
          ...(dialog?.slotProps?.paper ?? {}),
          sx: {
            border: '1px solid transparent',
            borderColor: frameColor,
            borderRadius: '14px',
            position: 'absolute',
            top: '11%',
            m: 0,
            overflow: 'hidden',
            boxShadow:
              '0px 12px 32px rgba(0,0,0,0.18), 0px 24px 64px rgba(0,0,0,0.16)',
            //@ts-ignore
            ...(dialog?.slotProps?.paper?.sx ?? {}),
          },
        },
      }}
      keepMounted={false}
      open={openState}
      maxWidth="sm"
      fullWidth
      onClose={() => setOpenState(false)}
      sx={{
        minHeight: 200,
      }}
    >
      <SearchContent
        setOpen={setOpenState}
        loading={loading}
        searchIcon={searchIcon}
        items={items}
        groups={groups}
        showFooter={showFooter}
        footerLabels={footerLabels}
        noResultsItem={noResultsItem}
        searchPlaceholder={searchPlaceholder}
        limit={limit}
      />
    </Dialog>
  );
}
