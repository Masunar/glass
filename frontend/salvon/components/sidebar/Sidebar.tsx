import Desktop from './variant/Desktop';
import Mobile from './variant/Mobile';

import type { SidebarProps } from '@salvon/components/sidebar/types';

export default function Sidebar(props: SidebarProps) {
  return (
    <>
      <Desktop {...props} />
      <Mobile {...props} />
    </>
  );
}
