import { Typography } from '@mui/material';

import CategoryIconBadge from './CategoryIconBadge';
import DataHubSearchField from './DataHubSearchField';
import ItemRow from './ItemRow';
import type {
  DataHubCategory,
  DataHubItem,
  DataHubLabels,
  DataHubProps,
  DataHubSlotProps,
} from './types.d';
import { type ReactNode, useState } from 'react';

import { Div, Flex } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';

export type CategoryDetailProps = {
  category: DataHubCategory;
  onBack: () => void;
  searchable?: boolean;
  filter: (item: DataHubItem, query: string) => boolean;
  labels?: DataHubLabels;
  searchIcon?: DataHubProps['searchIcon'];
  rowChevron?: DataHubProps['rowChevron'];
  renderRow?: (item: DataHubItem) => ReactNode;
  slotProps?: DataHubSlotProps;
};

export default function CategoryDetail({
  category,
  onBack,
  searchable = true,
  filter,
  labels,
  searchIcon,
  rowChevron,
  renderRow,
  slotProps,
}: CategoryDetailProps) {
  const [search, setSearch] = useState('');
  const query = search.trim();
  const items = query
    ? category.items.filter((item) => filter(item, query))
    : category.items;

  return (
    <Flex column sx={{ gap: 2 }}>
      <Flex aCenter sx={{ gap: 1.2 }}>
        <IconButton
          preset="return"
          onClick={onBack}
          label={labels?.back}
          {...slotProps?.backButton}
          sx={{ width: 36, height: 36, ...slotProps?.backButton?.sx }}
        />
        <Flex column>
          <Typography
            sx={{ fontWeight: 600, fontSize: '0.94rem', lineHeight: 1.3 }}
          >
            {category.label}
          </Typography>
          <Typography
            variant="body2"
            color="text.secondary"
            sx={{ fontSize: '0.85rem', lineHeight: 0.92 }}
          >
            {labels?.count?.(category.items.length) ?? category.items.length}
          </Typography>
        </Flex>
      </Flex>

      {searchable && (
        <DataHubSearchField
          value={search}
          onChange={setSearch}
          placeholder={labels?.searchInCategory}
          icon={searchIcon}
          slotProps={{ field: slotProps?.search }}
        />
      )}

      <Div
        {...slotProps?.grid}
        sx={{
          display: 'grid',
          gap: 1,
          gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' },
          ...slotProps?.grid?.sx,
        }}
      >
        {items.map((item, index) =>
          renderRow ? (
            <Div key={`${category.id}-${index}`}>{renderRow(item)}</Div>
          ) : (
            <ItemRow
              key={`${category.id}-${index}`}
              item={item}
              categoryIcon={category?.icon?.element}
              chevron={rowChevron}
              category={category}
              slotProps={{
                row: slotProps?.row,
                rowLink: slotProps?.rowLink,
                rowLabel: slotProps?.rowLabel,
              }}
            />
          ),
        )}
      </Div>
    </Flex>
  );
}
