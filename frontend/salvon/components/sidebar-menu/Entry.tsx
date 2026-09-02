import Entries from './Entries';
import { menuEntryType } from './const';
import Divider from './entry-type/Divider';
import Header from './entry-type/Header';
import Item from './entry-type/Item';
import Dropdown from './entry-type/dropdown/Dropdown';
import type { HasPermissionToCallback, MenuEntry } from './types.d';
import { createElement } from 'react';

import { usePalette } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { isFunction, isString } from '@salvon/utils/type-check';

interface Props {
  compactMode: boolean;
  isNested?: boolean;
  entry: MenuEntry;
  hasPermissionTo?: HasPermissionToCallback;
}

export default function Entry({
  compactMode,
  entry,
  hasPermissionTo,
  isNested = false,
}: Props) {
  const palette = usePalette();
  const t = useTranslation();
  const path = !entry.route
    ? '#'
    : ((isString(entry.route) ? entry.route : entry.route.path) ?? '');

  const translationKey = entry.translation;
  const entryTitle = isFunction(entry.label)
    ? createElement(entry.label)
    : entry.label || '#';

  const title = translationKey ? t(translationKey) : entryTitle;

  if (!hasPermissionTo) {
    hasPermissionTo = () => true;
  }

  const canAccess = (item: MenuEntry): boolean => {
    const route = typeof item.route === 'object' ? item.route : undefined;
    return hasPermissionTo(route?.permissions);
  };

  if (!canAccess(entry)) {
    return <></>;
  }

  if (entry.children && !entry.children?.find(canAccess)) {
    return <></>;
  }

  if (entry.type === menuEntryType.divider) {
    return (
      <Divider
        sx={{
          ...((palette?.salvon?.menu?.divider ?? {}) as any),
          ...(entry.sx ?? {}),
        }}
      />
    );
  }

  if (entry.type === menuEntryType.header) {
    const combinedSx = {
      ...entry.sx,
      ...(compactMode ? entry.collapsedSx : {}),
    };

    return (
      <Header compactMode={compactMode} sx={combinedSx}>
        {title}
      </Header>
    );
  }

  if (entry.type === menuEntryType.group) {
    return <Entries items={entry.children ?? []} compactMode={compactMode} />;
  }

  if (entry.children) {
    return (
      <Dropdown
        compactMode={compactMode}
        title={title}
        icon={entry.icon}
        items={entry.children}
        isNested={isNested}
        sx={entry.sx}
        regex={entry.regex}
        hasPermissionTo={hasPermissionTo}
      />
    );
  }

  return (
    <Item
      compactMode={compactMode}
      path={path}
      title={title}
      icon={entry.icon}
      isNested={isNested}
      sx={entry.sx}
      target={entry.target}
      regex={entry.regex}
    />
  );
}
