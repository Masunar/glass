import { Typography } from '@mui/material';

import type { ComponentType, ReactNode } from 'react';

import ToggleRow from './ToggleRow';
import type { ToggleCenterLabels, ToggleCenterSlotProps, ToggleGroup } from './types.d';

import { AccentIcon } from '@salvon/components/accent';
import { BaseButton } from '@salvon/components/button';
import { Div, Flex } from '@salvon/components/div';
import type { SettingsHeaderProps } from '@salvon/components/settings-center';
import type { SwitchProps, SwitchVariant } from '@salvon/components/switch';

export type ToggleGroupPanelProps = {
  group: ToggleGroup;
  values: Record<string, boolean>;
  onToggle: (optionId: string, next: boolean) => void;
  onSelectAll: () => void;
  onClear: () => void;
  /** Shared fixed-height header wrapper from SettingsCenter's render ctx. */
  Header: ComponentType<SettingsHeaderProps>;
  /** True on mobile — Header renders the back button, so hide the icon. */
  onBack?: () => void;
  /** Default footer (from ToggleCenter); group's own `footer` overrides it. */
  footer?: ReactNode;
  labels: ToggleCenterLabels;
  switchVariant?: SwitchVariant;
  switchProps?: SwitchProps;
  slotProps?: ToggleCenterSlotProps;
};

export default function ToggleGroupPanel({
  group,
  values,
  onToggle,
  onSelectAll,
  onClear,
  Header,
  onBack,
  footer,
  labels,
  switchVariant,
  switchProps,
  slotProps,
}: ToggleGroupPanelProps) {
  const enabled = group.options.filter((o) => !o.disabled);
  const granted = enabled.filter((o) => values[o.id]).length;
  const total = enabled.length;
  const resolvedFooter = group.footer ?? footer;

  return (
    <Flex column>
      <Header>
        {!onBack && group.icon && <AccentIcon>{group.icon}</AccentIcon>}
        <Flex column sx={{ flex: '1 1 auto', minWidth: 140 }}>
          <Typography
            {...slotProps?.panelTitle}
            sx={{
              fontWeight: 700,
              fontSize: '1.05rem',
              color: 'text.primary',
              ...slotProps?.panelTitle?.sx,
            }}
          >
            {group.label}
          </Typography>
          {labels.granted && (
            <Typography variant="body2" color="text.secondary">
              {labels.granted(granted, total)}
            </Typography>
          )}
        </Flex>

        <Flex aCenter sx={{ gap: 1, flexShrink: 0 }}>
          <BaseButton
            variant="text"
            size="small"
            onClick={onSelectAll}
            sx={{ fontWeight: 600 }}
          >
            {labels.selectAll}
          </BaseButton>
          <BaseButton
            variant="text"
            size="small"
            color="inherit"
            onClick={onClear}
            sx={{ fontWeight: 600, color: 'text.secondary' }}
          >
            {labels.clear}
          </BaseButton>
        </Flex>
      </Header>

      <Div>
        {group.options.map((option) => (
          <ToggleRow
            key={option.id}
            option={option}
            checked={!!values[option.id]}
            onToggle={(next) => onToggle(option.id, next)}
            variant={switchVariant}
            switchProps={switchProps}
            slotProps={{
              label: slotProps?.rowLabel,
              description: slotProps?.rowDescription,
            }}
          />
        ))}
      </Div>

      {resolvedFooter && <Div sx={{ mt: 1.5 }}>{resolvedFooter}</Div>}
    </Flex>
  );
}
