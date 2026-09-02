import { Tooltip } from '@mui/material';

import { PiCaretLeft, PiCaretRight } from 'react-icons/pi';

import { Flex } from '@salvon/components/div';
import { useMenuControl } from '@salvon/hooks/useMenuControl';
import { usePalette } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';

export type SidebarCollapseProps = {
  width?: number;
  compactWidth?: number;
};

export default function SidebarCollapse({
  width = 280,
  compactWidth = 90,
}: SidebarCollapseProps) {
  const { compactMode, toggleCompactMode, mobileOpen } = useMenuControl();
  const t = useTranslation();
  const palette = usePalette();

  const renderCompactMode = compactMode && !mobileOpen;
  const sidebarWidth = renderCompactMode ? compactWidth : width;

  const isDark = palette.mode === 'dark';
  const mutedText = isDark ? '#c4c4c4' : '#64748b';
  const toggle = {
    bg: isDark ? '#1a1a1a' : '#ffffff',
    border: isDark ? '#2c2c2c' : '#e2e8f0',
    hover: isDark ? '#222222' : '#f1f5f9',
  };

  return (
    <Tooltip title={renderCompactMode ? t('expand') : t('collapse')}>
      <Flex
        center
        onClick={toggleCompactMode}
        sx={{
          display: { xs: 'none', md: 'flex' },
          position: 'fixed',
          top: 32,
          left: `${sidebarWidth}px`,
          transform: 'translate(-50%, -50%)',
          zIndex: (theme) => theme.zIndex.drawer + 1,
          width: 26,
          height: 26,
          borderRadius: '8px',
          border: `1px solid ${toggle.border}`,
          background: toggle.bg,
          color: mutedText,
          cursor: 'pointer',
          boxShadow: '0 1px 3px rgba(15,23,42,0.08)',
          '&:hover': { background: toggle.hover },
        }}
      >
        {renderCompactMode ? (
          <PiCaretRight size={15} />
        ) : (
          <PiCaretLeft size={15} />
        )}
      </Flex>
    </Tooltip>
  );
}
