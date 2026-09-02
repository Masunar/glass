import Collapse from './Collapse';
import Popover from './Popover';
import type { MenuDropdown } from './types.d';

export default function Dropdown(props: MenuDropdown) {
  if (props.compactMode) {
    return <Popover {...props} />;
  }

  return <Collapse {...props} />;
}
