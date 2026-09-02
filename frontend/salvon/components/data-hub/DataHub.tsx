import { Typography } from '@mui/material';

import CategoryCard from './CategoryCard';
import CategoryDetail from './CategoryDetail';
import DataHubSearchField from './DataHubSearchField';
import ItemRow from './ItemRow';
import type { DataHubItem, DataHubLabels, DataHubProps } from './types.d';
import { useMemo, useState } from 'react';
import { GrBook } from 'react-icons/gr';

import { AccentIcon, HeadingTypography } from '@salvon/components/accent';
import { Div, Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

const defaultFilter = (item: DataHubItem, query: string) => {
  const q = query.toLowerCase();
  return (
    item.label.toLowerCase().includes(q) ||
    (item.keywords?.toLowerCase().includes(q) ?? false)
  );
};

export default function DataHub({
  categories,
  heading,
  columns,
  searchable = true,
  sampleCount,
  searchIcon,
  rowChevron,
  selectedId,
  onSelect,
  onSearch,
  filter = defaultFilter,
  labels,
  renderHeader,
  renderCard,
  renderRow,
  renderDetail,
  slotProps,
}: DataHubProps) {
  const t = useTranslation();
  const [search, setSearch] = useState('');
  const [internalId, setInternalId] = useState<string | null>(null);

  const activeId = selectedId ?? internalId;

  const resolvedLabels: DataHubLabels = {
    back: t('return'),
    noResults: t('no_results'),
    searchPlaceholder: t('search'),
    ...labels,
  };

  const setSelected = (id: string | null) => {
    onSelect?.(id);
    if (selectedId === undefined) setInternalId(id);
  };

  const handleSearch = (value: string) => {
    setSearch(value);
    onSearch?.(value);
  };

  const totalItems = useMemo(
    () => categories.reduce((sum, c) => sum + c.items.length, 0),
    [categories],
  );

  const query = search.trim();
  const searchResults = useMemo(() => {
    if (!query) return [];
    return categories.flatMap((c) =>
      c.items
        .map((item, index) => ({ item, key: `${c.id}-${index}` }))
        .filter(({ item }) => filter(item, query)),
    );
  }, [query, categories, filter]);

  const selected = categories.find((c) => c.id === activeId) ?? null;

  if (selected) {
    if (renderDetail) {
      return <>{renderDetail(selected, () => setSelected(null))}</>;
    }
    return (
      <CategoryDetail
        category={selected}
        onBack={() => setSelected(null)}
        searchable={searchable}
        filter={filter}
        labels={resolvedLabels}
        searchIcon={searchIcon}
        rowChevron={rowChevron}
        renderRow={renderRow}
        slotProps={slotProps}
      />
    );
  }

  const gridColumns = columns ?? {
    xs: '1fr',
    sm: '1fr 1fr',
    lg: 'repeat(4, 1fr)',
  };

  const rowGrid = (
    <Div
      {...slotProps?.grid}
      sx={{
        display: 'grid',
        gap: 1,
        gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' },
        ...slotProps?.grid?.sx,
      }}
    >
      {searchResults.map(({ item, key }) =>
        renderRow ? (
          <Div key={key}>{renderRow(item)}</Div>
        ) : (
          <ItemRow
            key={key}
            item={item}
            chevron={rowChevron}
            slotProps={{
              row: slotProps?.row,
              rowLink: slotProps?.rowLink,
              rowLabel: slotProps?.rowLabel,
            }}
          />
        ),
      )}
    </Div>
  );

  return (
    <Flex column {...slotProps?.root} sx={{ gap: 2, ...slotProps?.root?.sx }}>
      {renderHeader
        ? renderHeader({ categories: categories.length, items: totalItems })
        : heading && (
            <Flex
              aCenter
              {...slotProps?.header}
              sx={{ gap: 1, ...slotProps?.header?.sx }}
            >
              <AccentIcon>{heading.icon ?? <GrBook />}</AccentIcon>
              <Flex column>
                <HeadingTypography {...slotProps?.headingTitle}>
                  {heading.title}
                </HeadingTypography>
                {resolvedLabels.summary && (
                  <Typography
                    variant="body2"
                    color="text.secondary"
                    {...slotProps?.summary}
                  >
                    {resolvedLabels.summary(categories.length, totalItems)}
                  </Typography>
                )}
              </Flex>
            </Flex>
          )}

      {searchable && (
        <DataHubSearchField
          value={search}
          onChange={handleSearch}
          placeholder={resolvedLabels.searchPlaceholder}
          icon={searchIcon}
          slotProps={{ field: slotProps?.search }}
        />
      )}

      {query ? (
        searchResults.length === 0 ? (
          <Typography color="text.secondary">
            {resolvedLabels.noResults}
          </Typography>
        ) : (
          rowGrid
        )
      ) : (
        <Div
          {...slotProps?.grid}
          sx={{
            display: 'grid',
            gap: 2,
            gridTemplateColumns: gridColumns,
            ...slotProps?.grid?.sx,
          }}
        >
          {categories.map((category) =>
            renderCard ? (
              <Div key={category.id}>
                {renderCard(category, () => setSelected(category.id))}
              </Div>
            ) : (
              <CategoryCard
                key={category.id}
                category={category}
                onOpen={setSelected}
                sampleCount={sampleCount}
                labels={resolvedLabels}
                slotProps={{
                  card: slotProps?.card,
                  title: slotProps?.title,
                  sample: slotProps?.sample,
                  iconBadge: slotProps?.iconBadge,
                }}
              />
            ),
          )}
        </Div>
      )}
    </Flex>
  );
}
