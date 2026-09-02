import {
  Checkbox,
  type CheckboxProps,
  Radio,
  type RadioProps,
  Typography,
  type TypographyProps,
} from '@mui/material';

import type { ReactNode } from 'react';

import { Flex, type FlexProps } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';
import type { SlotItem } from '@salvon/types';

export type ControlCardProps = {
  label?: ReactNode;
  description?: ReactNode;
  checked?: boolean;
  disabled?: boolean;
  onClick?: () => void;
  control: 'radio' | 'checkbox';
  slotProps?: {
    root?: SlotItem<FlexProps>;
    control?: SlotItem<RadioProps & CheckboxProps>;
    content?: SlotItem<FlexProps>;
    label?: SlotItem<TypographyProps>;
    description?: SlotItem<TypographyProps>;
  };
};

export default function ControlCard({
  label,
  description,
  checked,
  disabled,
  onClick,
  control,
  slotProps,
}: ControlCardProps) {
  const palette = usePalette();
  const Control = control === 'radio' ? Radio : Checkbox;

  return (
    <Flex
      align={description ? 'flex-start' : 'center'}
      gap={1}
      className={checked ? 'checked' : undefined}
      onClick={disabled ? undefined : onClick}
      {...slotProps?.root}
      //@ts-ignore
      sx={{
        flex: 1,
        p: 1.5,
        borderRadius: 2,
        border: '1px solid',
        borderColor: 'divider',
        backgroundColor: 'transparent',
        cursor: disabled ? 'default' : 'pointer',
        opacity: disabled ? 0.5 : 1,
        '&.checked': {
          borderColor: 'primary.main',
          backgroundColor: 'action.hover',
        },
        '&:hover': disabled ? undefined : { borderColor: 'text.disabled' },
        '&.checked:hover': { borderColor: 'primary.main' },
        ...((palette?.salvon?.control_card ?? {}) as any),
        ...slotProps?.root?.sx,
      }}
    >
      <Control
        checked={checked}
        disabled={disabled}
        disableRipple
        {...slotProps?.control}
        sx={{ p: 0, mt: 0, ...slotProps?.control?.sx }}
      />
      <Flex column gap={0.25} {...slotProps?.content}>
        {label && (
          <Typography
            variant="body2"
            {...slotProps?.label}
            sx={{
              fontWeight: 600,
              color: 'text.primary',
              ...slotProps?.label?.sx,
            }}
          >
            {label}
          </Typography>
        )}
        {description && (
          <Typography
            variant="caption"
            {...slotProps?.description}
            sx={{ color: 'text.secondary', ...slotProps?.description?.sx }}
          >
            {description}
          </Typography>
        )}
      </Flex>
    </Flex>
  );
}
