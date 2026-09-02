import ToggleGroupPanel from './ToggleGroupPanel';
import type {
  ToggleCenterLabels,
  ToggleCenterProps,
  ToggleCenterValue,
} from './types.d';
import { useMemo, useState } from 'react';

import {
  SettingsCenter,
  type SettingsCenterItemData,
} from '@salvon/components/settings-center';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function ToggleCenter({
  groups,
  value,
  defaultValue,
  onChange,
  onToggle: onToggleProp,
  switchVariant = 'plain',
  switchProps,
  footer,
  labels,
  slotProps,
  ...settingsProps
}: ToggleCenterProps) {
  const t = useTranslation();

  // Fill every passed group/option with an explicit boolean so both the
  // emitted value and internal state are always complete (never sparse).
  const normalize = (partial: ToggleCenterValue): ToggleCenterValue =>
    Object.fromEntries(
      groups.map((g) => [
        g.id,
        Object.fromEntries(
          g.options.map((o) => [o.id, !!partial[g.id]?.[o.id]]),
        ),
      ]),
    );

  const [internal, setInternal] = useState<ToggleCenterValue>(() =>
    normalize(defaultValue ?? {}),
  );
  const values = normalize(value ?? internal);

  const resolvedLabels: ToggleCenterLabels = {
    searchPlaceholder: t('search'),
    noResults: t('no_results'),
    selectAll: t('select_all'),
    clear: t('clear'),
    granted: (granted, total) => `${granted} / ${total}`,
    ...labels,
  };

  const groupValues = (groupId: string) => values[groupId] ?? {};

  const selectedOnly = (full: ToggleCenterValue): ToggleCenterValue =>
    Object.fromEntries(
      Object.entries(full)
        .map(([gid, opts]) => [
          gid,
          Object.fromEntries(Object.entries(opts).filter(([, on]) => on)),
        ])
        .filter(([, opts]) => Object.keys(opts as object).length > 0),
    );

  const commit = (
    next: ToggleCenterValue,
    changed: { groupId: string; optionId?: string },
  ): ToggleCenterValue => {
    const full = normalize(next);
    onChange?.(full, selectedOnly(full), changed);
    if (value === undefined) setInternal(full);
    return full;
  };

  const setGroup = (groupId: string, groupNext: Record<string, boolean>) => ({
    ...values,
    [groupId]: groupNext,
  });

  // Merge a sparse {group:{opt:bool}} patch over a base matrix.
  const mergePatch = (base: ToggleCenterValue, patch: ToggleCenterValue) => {
    const out: ToggleCenterValue = { ...base };
    for (const [gid, opts] of Object.entries(patch)) {
      out[gid] = { ...out[gid], ...opts };
    }
    return out;
  };

  const toggle = (groupId: string, optionId: string, on: boolean) => {
    const full = commit(
      setGroup(groupId, { ...groupValues(groupId), [optionId]: on }),
      { groupId, optionId },
    );
    if (!onToggleProp) return;
    // Helpers build off `full` (post-change) so chained edits don't use
    // stale closure state; each helper commits on top of the last.
    let current = full;
    onToggleProp({
      groupId,
      optionId,
      enabled: on,
      get allValues() {
        return current;
      },
      setOption: (g, o, next) => {
        current = commit(mergePatch(current, { [g]: { [o]: next } }), {
          groupId: g,
          optionId: o,
        });
      },
      setOptions: (patch) => {
        current = commit(mergePatch(current, patch), { groupId });
      },
    });
  };

  const setAll = (groupId: string, on: boolean) => {
    const group = groups.find((g) => g.id === groupId);
    if (!group) return;
    const next: Record<string, boolean> = { ...groupValues(groupId) };
    for (const option of group.options) {
      if (!option.disabled) next[option.id] = on;
    }
    commit(setGroup(groupId, next), { groupId });
  };

  const items: SettingsCenterItemData[] = useMemo(
    () =>
      groups.map((group) => {
        const enabled = group.options.filter((o) => !o.disabled);
        const granted = enabled.filter(
          (o) => groupValues(group.id)[o.id],
        ).length;
        return {
          id: group.id,
          label: group.label,
          icon: group.icon,
          group: group.section,
          keywords: group.keywords,
          disabled: group.disabled,
          count: `${granted}/${enabled.length}`,
          render: (item, ctx) => {
            const g = groups.find((x) => x.id === item.id)!;
            return (
              <ToggleGroupPanel
                group={g}
                values={groupValues(g.id)}
                onToggle={(optionId, next) => toggle(g.id, optionId, next)}
                onSelectAll={() => setAll(g.id, true)}
                onClear={() => setAll(g.id, false)}
                Header={ctx.Header}
                onBack={ctx.onBack}
                footer={footer}
                labels={resolvedLabels}
                switchVariant={switchVariant}
                switchProps={switchProps}
                slotProps={slotProps}
              />
            );
          },
        };
      }),
    // values drive the count + panel; recompute on any change
    [groups, values, resolvedLabels, switchVariant, switchProps, footer, slotProps],
  );

  return (
    <SettingsCenter
      items={items}
      labels={{
        searchPlaceholder: resolvedLabels.searchPlaceholder,
        noResults: resolvedLabels.noResults,
      }}
      slotProps={slotProps}
      {...settingsProps}
    />
  );
}
