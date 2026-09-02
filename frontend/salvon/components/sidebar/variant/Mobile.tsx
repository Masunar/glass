import Drawer from '@mui/material/Drawer';

import ScrollableContent from '../ScrollableContent';

import type { SidebarProps } from '@salvon/components/sidebar/types';
import { useMenuControl } from '@salvon/hooks/useMenuControl';

export default function Mobile({
  width,
  children,
  padding,
  slotProps,
}: SidebarProps) {
  const { drawer } = slotProps ?? {};
  const { mobileOpen, hideMobile } = useMenuControl();

  return (
    <Drawer
      {...drawer}
      variant="temporary"
      open={mobileOpen}
      onClose={hideMobile}
      ModalProps={{
        keepMounted: true, // Better open performance on mobile.
      }}
      sx={{
        display: { xs: 'block', md: 'none' },
        '& .MuiDrawer-paper': {
          boxSizing: 'border-box',
          width: width,
        },
        ...(drawer?.sx ?? {}),
      }}
    >
      <ScrollableContent width={width} padding={padding} hidden={!mobileOpen}>
        {mobileOpen && <>{children}</>}
      </ScrollableContent>
    </Drawer>
  );
}
