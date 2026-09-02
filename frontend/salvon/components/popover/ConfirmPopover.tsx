import { type CSSProperties, type ReactNode } from 'react';
import {
  PiCheckCircleFill,
  PiInfoFill,
  PiQuestionFill,
  PiTrashFill,
  PiWarningCircleFill,
} from 'react-icons/pi';

import { Button, type ButtonProps } from '@salvon/components/button';
import { Div, Flex, type FlexProps } from '@salvon/components/div';
import { Popover, type PopoverProps } from '@salvon/components/popover';
import { usePalette } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';
import type { Noop } from '@salvon/types';

type AccentColor = 'error' | 'success' | 'info' | 'warning' | 'primary';

export type ConfirmPopoverPreset =
  'delete' | 'success' | 'info' | 'warning' | 'confirm';

type PresetConfig = {
  icon: ReactNode;
  color: AccentColor;
  confirmKey: string;
};

const presets: Record<ConfirmPopoverPreset, PresetConfig> = {
  delete: { icon: <PiTrashFill />, color: 'error', confirmKey: 'delete' },
  success: { icon: <PiCheckCircleFill />, color: 'success', confirmKey: 'yes' },
  info: { icon: <PiInfoFill />, color: 'info', confirmKey: 'confirm' },
  warning: {
    icon: <PiWarningCircleFill />,
    color: 'warning',
    confirmKey: 'confirm',
  },
  confirm: {
    icon: <PiQuestionFill />,
    color: 'primary',
    confirmKey: 'confirm',
  },
};

export type ConfirmPopoverProps = Omit<PopoverProps, 'children'> & {
  preset?: ConfirmPopoverPreset;
  icon?: ReactNode;
  iconColor?: string;
  iconBg?: string;
  color?: AccentColor;
  arrow?: boolean;
  title?: ReactNode;
  description?: ReactNode;
  /** Escape hatch — replaces the built-in title/description body. */
  label?: ReactNode;
  onConfirm?: (closePopover: Noop) => void;
  onCancel?: (closePopover: Noop) => void;
  loading?: boolean;
  confirmTitle?: ReactNode;
  cancelTitle?: ReactNode;
  footerJustify?: CSSProperties['justifyContent'];
  slotProps?: {
    confirmButton?: Omit<ButtonProps, 'onClick' | 'loading'>;
    cancelButton?: Omit<ButtonProps, 'onClick' | 'loading'>;
    footer?: FlexProps;
    icon?: FlexProps;
  };
};

export default function ConfirmPopover({
  preset,
  icon,
  iconColor,
  iconBg,
  color,
  title,
  description,
  loading,
  label,
  onConfirm,
  onCancel,
  confirmTitle,
  cancelTitle,
  slotProps,
  footerJustify = 'end',
  arrow = false,
  placement = 'top-center',
  ...props
}: ConfirmPopoverProps) {
  const t = useTranslation();
  const palette = usePalette();
  const {
    confirmButton,
    cancelButton,
    footer,
    icon: iconSlot,
  } = slotProps ?? {};

  const config = preset ? presets[preset] : undefined;
  const accentColor = color ?? config?.color ?? 'primary';
  const accentIcon = icon ?? config?.icon;
  const paletteColor = `${accentColor}.main`;

  if (!onCancel) {
    onCancel = (closePopover: Noop) => closePopover();
  }

  if (!onConfirm) {
    onConfirm = (closePopover: Noop) => closePopover();
  }

  const body = label ?? (
    <Flex align="top" gap="10px">
      {accentIcon && (
        <Flex
          center
          sx={{
            flex: '0 0 auto',
            width: 36,
            height: 36,
            borderRadius: '10px',
            fontSize: '1.15rem',
            color: iconColor ?? '#fff',
            backgroundColor: iconBg ?? paletteColor,
          }}
          {...iconSlot}
        >
          {accentIcon}
        </Flex>
      )}
      <Div sx={{ flex: 1, minWidth: 0 }}>
        {title && (
          <Div
            sx={{
              mt: '1px',
              fontWeight: 700,
              fontSize: '0.92rem',
              lineHeight: 1.2,
              color: 'text.primary',
            }}
          >
            {title}
          </Div>
        )}
        {description && (
          <Div
            sx={{
              fontSize: '0.81rem',
              lineHeight: 1.35,
              color: 'text.secondary',
            }}
          >
            {description}
          </Div>
        )}
      </Div>
    </Flex>
  );

  return (
    <Popover
      loading={loading}
      placement={placement}
      sx={{
        mt: '-5px',
      }}
      slotProps={{
        paper: {
          sx: {
            borderRadius: '14px',
            border: '1px solid',
            borderColor: 'divider',
            backgroundColor: 'background.paper',
            overflow: 'visible',
            ...((palette?.salvon?.popover?.paper ?? { boxShadow: 3 }) as any),
            ...(arrow &&
              placement === 'top-center' && {
                '&::after': {
                  content: '""',
                  position: 'absolute',
                  bottom: '-7px',
                  left: '50%',
                  transform: 'translateX(-50%) rotate(45deg)',
                  width: 12,
                  height: 12,
                  backgroundColor: 'inherit',
                  borderRight: '1px solid',
                  borderBottom: '1px solid',
                  borderColor: 'divider',
                  borderBottomRightRadius: '3px',
                },
              }),
          },
        },
      }}
      {...props}
    >
      {({ closePopover }) => (
        <Div
          sx={{
            padding: '12px 16px',
            minWidth: { xs: 'auto', sm: 360 },
            maxWidth: { xs: '100%', sm: 420 },
          }}
        >
          {body}
          <Flex justify={footerJustify} gap="8px" mt="14px" {...footer}>
            <Button
              variant="outlined"
              color="inherit"
              size="small"
              sx={{
                px: '11px',
                py: '4px',
                minWidth: 'auto',
                minHeight: 'auto',
                height: '27px',
                fontSize: '0.78rem',
                fontWeight: 600,
                color: 'text.secondary',
                borderColor: 'divider',
                '&:hover': {
                  borderColor: 'text.disabled',
                  backgroundColor: 'action.hover',
                },
              }}
              {...cancelButton}
              disabled={loading}
              onClick={() => onCancel(closePopover)}
            >
              {cancelTitle ?? t('no')}
            </Button>
            <Button
              variant="contained"
              color={accentColor}
              size="small"
              sx={{
                px: '11px',
                py: '4px',
                minWidth: 'auto',
                minHeight: 'auto',
                height: '27px',
                fontSize: '0.78rem',
                fontWeight: 700,
              }}
              {...confirmButton}
              loading={loading}
              onClick={() => onConfirm(closePopover)}
            >
              {confirmTitle ?? t(config?.confirmKey ?? 'yes')}
            </Button>
          </Flex>
        </Div>
      )}
    </Popover>
  );
}
