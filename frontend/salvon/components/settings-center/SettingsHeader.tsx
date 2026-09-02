import type { ReactNode } from 'react';

import { Flex, type FlexProps } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';
import { usePalette } from '@salvon/hooks/useTheme';

export type SettingsHeaderProps = {
  children: ReactNode;
  /** Mobile back handler; renders a leading return button when set. */
  onBack?: () => void;
  backLabel?: string;
  /** Band height + content-pane padding — SettingsCenter binds these so the
   *  header always matches the sidebar search band and pane edges. */
  headerHeight?: number;
  contentPx?: number;
  contentPy?: number;
  sx?: FlexProps['sx'];
};

/**
 * Fixed-height panel header for the content pane. Owns the header height and
 * the full-bleed bottom divider so it always lines up with the sidebar search
 * band — consumers just drop their title/actions inside.
 */
export default function SettingsHeader({
  children,
  onBack,
  backLabel,
  headerHeight = 74,
  contentPx = 3,
  contentPy = 2,
  sx,
}: SettingsHeaderProps) {
  const palette = usePalette();

  return (
    <Flex
      aCenter
      wrap
      sx={{
        gap: 1.5,
        minHeight: headerHeight,
        // pull up/out to sit flush against the pane top and bleed the divider
        mt: -contentPy,
        mx: -contentPx,
        px: contentPx,
        mb: contentPy,
        borderBottom: '1px solid',
        borderColor: 'divider',
        ...((palette.salvon?.settings_center?.header ?? {}) as object),
        ...sx,
      }}
    >
      {onBack && (
        <IconButton
          preset="return"
          onClick={onBack}
          label={backLabel}
          sx={{ width: 36, height: 36 }}
        />
      )}
      {children}
    </Flex>
  );
}
