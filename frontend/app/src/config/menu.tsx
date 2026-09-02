import { appRoutes } from '../router/app-router';
import { BiSolidDashboard } from 'react-icons/bi';
import { PiChartLine, PiUserCircleGear } from 'react-icons/pi';

import type { MenuEntries } from '@salvon/components/sidebar-menu';

export const menu: MenuEntries = [
  {
    type: 'header',
    translation: 'page.menu.header',
    sx: {
      paddingTop: '0 !important',
    },
  },
  {
    icon: BiSolidDashboard,
    translation: 'page.menu.dashboard',
    route: appRoutes.index,
  },
  {
    type: 'header',
    translation: 'page.menu.administration',
  },
  {
    icon: PiUserCircleGear,
    translation: 'page.menu.users',
    route: appRoutes.users,
  },
];

export default menu;
