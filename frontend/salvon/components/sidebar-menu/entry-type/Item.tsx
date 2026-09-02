import { type SxProps } from '@mui/material';

import CompactMenuTooltip from '../components/CompactMenuTooltip';
import DotIcon from '../components/DotIcon';
import ListButton from '../components/ListButton';
import ListIcon from '../components/ListIcon';
import {
  type FunctionComponent,
  type ReactNode,
  createElement,
  useEffect,
} from 'react';
import { Link as RouterLink, useLocation, useMatch } from 'react-router';

import { Div, Flex } from '@salvon/components/div';
import { useMenuControl } from '@salvon/hooks/useMenuControl';
import { usePalette } from '@salvon/hooks/useTheme';
import { entryMatchesRegex } from '@salvon/utils/menu';

interface Props {
  compactMode: boolean;
  isNested?: boolean;
  path?: string;
  title: ReactNode;
  icon?: string | FunctionComponent;
  sx?: SxProps;
  permissions?: Array<string>;
  target?: '_self' | '_blank' | '_parent' | '_top';
  regex?: string | RegExp;
  mobileCloseOnClick?: boolean;
}

export default function Item({
  compactMode,
  path,
  icon,
  title,
  regex,
  mobileCloseOnClick,
  isNested = false,
  ...props
}: Props) {
  const location = useLocation();
  const isActive =
    path === undefined
      ? false
      : useMatch(path) || entryMatchesRegex(regex, location.pathname);
  const { mobileOpen, hideMobile } = useMenuControl();
  const badge = usePalette().salvon?.menu?.list_icon_badge;

  useEffect(() => {
    if (isActive && mobileOpen && mobileCloseOnClick) {
      hideMobile();
    }
  }, [isActive]);

  return (
    <CompactMenuTooltip
      compactMode={compactMode}
      isNested={isNested}
      title={title}
    >
      <ListButton
        className={isActive ? 'active active-route' : ''}
        component={path === undefined ? 'div' : RouterLink}
        //@ts-ignore
        to={path}
        {...props}
      >
        <Flex center fw={compactMode}>
          {!isNested && icon && (
            <ListIcon
              sx={
                compactMode
                  ? { minWidth: 'auto', width: 'auto', marginRight: 0 }
                  : undefined
              }
            >
              <Div sx={badge as any}>{createElement(icon)}</Div>
            </ListIcon>
          )}
          {!compactMode && isNested && <DotIcon isActive={!!isActive} />}
          {(!compactMode || isNested || !icon) && title}
        </Flex>
      </ListButton>
    </CompactMenuTooltip>
  );
}
