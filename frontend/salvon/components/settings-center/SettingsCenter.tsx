import { Typography } from '@mui/material';

import SettingsCenterItem from './SettingsCenterItem';
import SettingsHeader, { type SettingsHeaderProps } from './SettingsHeader';
import type {
  SettingsCenterItem as Item,
  SettingsCenterLabels,
  SettingsCenterProps,
} from './types.d';
import { useMemo, useState } from 'react';

import { SearchField } from '@salvon/components/_shared/search-field';
import { Div, Flex } from '@salvon/components/div';
import { OverlayLoading } from '@salvon/components/progress';
import { useIsUnder } from '@salvon/hooks/useMediaQuery';
import { usePalette } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';

const defaultFilter = (item: Item, query: string) => {
  const q = query.toLowerCase();
  return (
    item.label.toLowerCase().includes(q) ||
    (item.description?.toLowerCase().includes(q) ?? false) ||
    (item.keywords?.toLowerCase().includes(q) ?? false)
  );
};

export default function SettingsCenter({
  items,
  searchable = true,
  searchIcon,
  sidebarWidth = 300,
  height,
  loading = false,
  loadingProps,
  layout,
  selectedId,
  defaultSelectedId,
  onSelect,
  onSearch,
  filter = defaultFilter,
  labels,
  renderItem,
  renderContent,
  slotProps,
}: SettingsCenterProps) {
  const t = useTranslation();
  const palette = usePalette();
  const isMobile = useIsUnder('lg');

  const {
    headerHeight = 74,
    contentPx = 2.5,
    contentPy = 1,
    sidebarPad = 1.5,
  } = layout ?? {};

  const firstEnabled = items.find((i) => !i.disabled)?.id ?? null;
  const [internalId, setInternalId] = useState<string | null>(
    defaultSelectedId ?? firstEnabled,
  );
  const [search, setSearch] = useState('');
  // On mobile the panes swap; this tracks whether the detail pane is open.
  const [mobileOpen, setMobileOpen] = useState(false);

  const activeId = selectedId ?? internalId;

  const resolvedLabels: SettingsCenterLabels = {
    searchPlaceholder: t('search'),
    noResults: t('no_results'),
    back: t('return'),
    ...labels,
  };

  const select = (id: string | null) => {
    onSelect?.(id);
    if (selectedId === undefined) setInternalId(id);
    if (id != null) setMobileOpen(true);
  };

  const backToList = () => setMobileOpen(false);

  const showList = !isMobile || !mobileOpen;
  const showContent = !isMobile || mobileOpen;

  const handleSearch = (value: string) => {
    setSearch(value);
    onSearch?.(value);
  };

  const query = search.trim();
  const visible = useMemo(
    () => (query ? items.filter((i) => filter(i, query)) : items),
    [query, items, filter],
  );

  // Group preserving first-seen order.
  const groups = useMemo(() => {
    const map = new Map<string, Item[]>();
    for (const item of visible) {
      const key = item.group ?? '';
      if (!map.has(key)) map.set(key, []);
      map.get(key)!.push(item);
    }
    return [...map.entries()];
  }, [visible]);

  const active = items.find((i) => i.id === activeId) ?? null;

  const onBack = isMobile ? backToList : undefined;
  // Bind the shared header so consumers get onBack + back label for free.
  const BoundHeader = (props: SettingsHeaderProps) => (
    <SettingsHeader
      onBack={onBack}
      backLabel={resolvedLabels.back}
      headerHeight={headerHeight}
      contentPx={contentPx}
      contentPy={contentPy}
      {...props}
    />
  );
  const renderCtx = { onBack, Header: BoundHeader };
  const content = active
    ? (active.render ?? renderContent)?.(active, renderCtx)
    : (resolvedLabels.emptyState ?? null);

  const body = (
    <Flex
      column={isMobile}
      {...slotProps?.root}
      sx={{
        alignItems: 'stretch',
        borderRadius: '12px',
        overflow: 'hidden',
        ...(height ? { height } : {}),
        ...((palette.salvon?.settings_center?.root ?? {}) as object),
        ...slotProps?.root?.sx,
      }}
    >
      {showList && (
        <Flex
          column
          {...slotProps?.sidebar}
          sx={{
            width: isMobile ? '100%' : sidebarWidth,
            flexShrink: 0,
            ...(isMobile
              ? {}
              : { borderRight: '1px solid', borderColor: 'divider' }),
            ...(height ? { overflow: 'hidden' } : {}),
            ...((palette.salvon?.settings_center?.sidebar ?? {}) as object),
            ...slotProps?.sidebar?.sx,
          }}
        >
          {searchable && (
            // Fixed band matching SettingsHeader so search lines up with the
            // panel title and shares its bottom divider.
            <Flex
              aCenter
              sx={{
                flexShrink: 0,
                px: sidebarPad,
                minHeight: headerHeight,
              }}
            >
              <SearchField
                value={search}
                onChange={handleSearch}
                placeholder={resolvedLabels.searchPlaceholder}
                icon={searchIcon}
                token={palette.salvon?.settings_center?.search_field as object}
                slotProps={{ field: slotProps?.search }}
              />
            </Flex>
          )}

          {visible.length === 0 ? (
            <Typography
              variant="body2"
              color="text.secondary"
              sx={{ px: 2.5, pt: 2 }}
            >
              {resolvedLabels.noResults}
            </Typography>
          ) : (
            <Div
              {...slotProps?.list}
              sx={{
                display: 'flex',
                flexDirection: 'column',
                gap: 2,
                py: sidebarPad,
                pt: 0,
                ...(height ? { overflowY: 'auto' } : {}),
                ...slotProps?.list?.sx,
              }}
            >
              {groups.map(([group, groupItems]) => (
                <Div key={group || '__root'}>
                  {group && (
                    <Typography
                      {...slotProps?.groupLabel}
                      sx={{
                        pl: 'calc(16px + 3px)', // row pl:2 (16px) + 3px accent border
                        pr: 2,
                        mb: 1,
                        fontSize: '0.7rem',
                        fontWeight: 700,
                        letterSpacing: '0.07em',
                        textTransform: 'uppercase',
                        color: 'text.disabled',
                        ...((palette.salvon?.settings_center?.groupLabel ??
                          {}) as object),
                        ...slotProps?.groupLabel?.sx,
                      }}
                    >
                      {group}
                    </Typography>
                  )}
                  <Flex column>
                    {groupItems.map((item) =>
                      renderItem ? (
                        <Div key={item.id}>
                          {renderItem(item, {
                            active: item.id === activeId,
                            select: () => select(item.id),
                          })}
                        </Div>
                      ) : (
                        <SettingsCenterItem
                          key={item.id}
                          item={item}
                          active={item.id === activeId}
                          onSelect={select}
                          slotProps={{ label: slotProps?.itemLabel }}
                        />
                      ),
                    )}
                  </Flex>
                </Div>
              ))}
            </Div>
          )}
        </Flex>
      )}

      {showContent && (
        <Div
          {...slotProps?.content}
          sx={{
            flex: 1,
            minWidth: 0,
            px: contentPx,
            py: contentPy,
            ...(height ? { overflowY: 'auto' } : {}),
            ...((palette.salvon?.settings_center?.content ?? {}) as object),
            ...slotProps?.content?.sx,
          }}
        >
          {content}
        </Div>
      )}
    </Flex>
  );

  return (
    <OverlayLoading
      loading={loading}
      {...loadingProps}
      sx={{
        borderRadius: '12px',
        ...(height ? { height } : {}),
        ...loadingProps?.sx,
      }}
      contentSx={{
        ...(height ? { height: '100%' } : {}),
        ...loadingProps?.contentSx,
      }}
    >
      {body}
    </OverlayLoading>
  );
}
