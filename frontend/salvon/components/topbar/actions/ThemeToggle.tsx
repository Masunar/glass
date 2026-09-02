import type { TooltipProps } from '@mui/material';

import { useContext, useEffect } from 'react';
import { type IconBaseProps } from 'react-icons';
import { PiMoon, PiSun } from 'react-icons/pi';

import { Div } from '@salvon/components/div';
import {
  BaseIconButton,
  type IconButtonProps,
} from '@salvon/components/icon-button';
import { themeMode } from '@salvon/consts/theme-mode';
import ThemeModeContext from '@salvon/context/ThemeModeContext';
import { usePalette } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';
import type { SlotItem } from '@salvon/types';

export type ThemeToggleProps = {
  lightModeTitle?: string;
  darkModeTitle?: string;
  slotProps?: {
    tooltip: SlotItem<TooltipProps>;
    icon: SlotItem<IconBaseProps>;
    iconButton: SlotItem<IconButtonProps>;
  };
};

export default function ThemeToggle({
  lightModeTitle,
  darkModeTitle,
  slotProps,
}: ThemeToggleProps) {
  const { icon, tooltip, iconButton } = slotProps ?? {};

  const t = useTranslation();
  const palette = usePalette();
  const { handleToggleTheme, setTheme } = useContext(ThemeModeContext);

  if (!lightModeTitle) {
    lightModeTitle = t('light_mode');
  }

  if (!darkModeTitle) {
    darkModeTitle = t('dark_mode');
  }

  const kbdHandler = (e: KeyboardEvent) => {
    if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'l') {
      setTheme(themeMode.light);
      e.preventDefault();
    }

    if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'd') {
      setTheme(themeMode.dark);
      e.preventDefault();
    }
  };

  useEffect(() => {
    window.addEventListener('keydown', kbdHandler);

    return () => {
      window.removeEventListener('keydown', kbdHandler);
    };
  }, []);

  return (
    <Div>
      <BaseIconButton
        {...iconButton}
        onClick={handleToggleTheme}
        slotProps={{ tooltip }}
        label={
          palette.mode === themeMode.light ? darkModeTitle : lightModeTitle
        }
      >
        {palette.mode === themeMode.light ? (
          <PiMoon {...icon} />
        ) : (
          <PiSun {...icon} />
        )}
      </BaseIconButton>
    </Div>
  );
}
