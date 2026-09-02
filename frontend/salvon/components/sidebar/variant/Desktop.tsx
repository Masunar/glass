import Drawer from '@mui/material/Drawer';

import ScrollableContent from '../ScrollableContent';

import type { SidebarProps } from '@salvon/components/sidebar/types';

export default function Desktop({
  width,
  children,
  padding,
  slotProps,
  sx,
}: SidebarProps) {
  const { drawer } = slotProps ?? {};

  return (
    <Drawer
      variant="permanent"
      sx={{
        display: { xs: 'none', md: 'block' },
        '& .MuiDrawer-paper': {
          boxSizing: 'border-box',
          width: width,
        },
        ...(drawer?.sx ?? {}),
        ...((sx ?? {}) as any),
      }}
      {...drawer}
      open
    >
      <ScrollableContent width={width} padding={padding}>
        {children}
      </ScrollableContent>
    </Drawer>
  );
}
