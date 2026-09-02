import {
  type SwitchProps,
  Typography,
  type TypographyProps,
} from '@mui/material';

import type { ToggleOption } from './types.d';

import { Flex } from '@salvon/components/div';
import { Switch, type SwitchVariant } from '@salvon/components/switch';
import { usePalette } from '@salvon/hooks/useTheme';
import { voc } from '@salvon/utils/object';

export type ToggleRowProps = {
  option: ToggleOption;
  checked: boolean;
  onToggle: (next: boolean) => void;
  variant?: SwitchVariant;
  switchProps?: SwitchProps;
  slotProps?: { label?: TypographyProps; description?: TypographyProps };
};

export default function ToggleRow({
  option,
  checked,
  onToggle,
  variant = 'plain',
  switchProps,
  slotProps,
}: ToggleRowProps) {
  const palette = usePalette();
  //@ts-ignore
  const primary = palette.primary?.main ?? '#254a94';
  const disabled = option.disabled;

  return (
    <Flex
      aCenter
      sx={{
        gap: 2,
        py: 1.5,
        ...voc(disabled, { opacity: 0.55 }),
        ...((palette.salvon?.settings_center?.toggleRow ?? {}) as object),
      }}
    >
      {option.icon && (
        <Flex
          center
          role="button"
          tabIndex={disabled ? -1 : 0}
          aria-pressed={checked}
          aria-label={option.label}
          onClick={() => !disabled && onToggle(!checked)}
          onKeyDown={(e) => {
            if (disabled) return;
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              onToggle(!checked);
            }
          }}
          sx={{
            width: 32,
            height: 32,
            flexShrink: 0,
            borderRadius: '8px',
            fontSize: 17,
            cursor: disabled ? 'not-allowed' : 'pointer',
            color: checked ? '#fff' : 'text.disabled',
            backgroundColor: checked ? primary : 'action.hover',
            transition: 'background-color 150ms, color 150ms',
            ...((palette.salvon?.settings_center?.toggleIcon ?? {}) as object),
            ...voc(
              checked,
              (palette.salvon?.settings_center?.toggleIconActive ??
                {}) as object,
            ),
          }}
        >
          {option.icon}
        </Flex>
      )}

      <Flex column sx={{ flex: 1, minWidth: 0, gap: 0.25 }}>
        <Typography
          variant="body1"
          {...slotProps?.label}
          sx={{
            fontWeight: 600,
            color: 'text.primary',
            ...slotProps?.label?.sx,
          }}
        >
          {option.label}
        </Typography>
        {option.description && (
          <Typography
            variant="body2"
            {...slotProps?.description}
            sx={{ color: 'text.secondary', ...slotProps?.description?.sx }}
          >
            {option.description}
          </Typography>
        )}
      </Flex>

      <Switch
        variant={variant}
        color="primary"
        checked={checked}
        disabled={disabled}
        onChange={(_, next) => onToggle(next)}
        {...switchProps}
      />
    </Flex>
  );
}
