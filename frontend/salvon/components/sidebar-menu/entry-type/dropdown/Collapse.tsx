import { Collapse as MuiCollapse } from '@mui/material';

import Entries from '../../Entries';
import CompactMenuTooltip from '../../components/CompactMenuTooltip';
import DotIcon from '../../components/DotIcon';
import ListButton from '../../components/ListButton';
import ListIcon from '../../components/ListIcon';
import type { MenuDropdown } from './types.d';
import { createElement, useEffect, useState } from 'react';
import { MdChevronRight, MdExpandLess, MdExpandMore } from 'react-icons/md';

import { Div, Flex } from '@salvon/components/div';
import { useHasActiveMenuEntry } from '@salvon/hooks/useHasActiveMenuEntry';
import { entryMatchesRegex } from '@salvon/utils/menu';

export default function Collapse({
  compactMode,
  items,
  icon,
  title,
  regex,
  isNested = false,
  hasPermissionTo,
  ...props
}: MenuDropdown) {
  const [openCollapse, setOpenCollapse] = useState(false);
  const isActive =
    useHasActiveMenuEntry(items) || entryMatchesRegex(regex, location.pathname);
  const toggleCollapse = () => {
    setOpenCollapse(!openCollapse);
  };

  useEffect(() => {
    if (compactMode) {
      setOpenCollapse(false);
    }
  }, [compactMode]);

  return (
    <Div>
      <CompactMenuTooltip compactMode={false} isNested={isNested} title={title}>
        <ListButton
          className={isActive || openCollapse ? 'active' : ''}
          onClick={toggleCollapse}
          {...props}
        >
          <Flex jBetween fw>
            <Flex>
              {icon && !isNested && <ListIcon>{createElement(icon)}</ListIcon>}
              {isNested && <DotIcon isActive={isActive} />}
              {title}
            </Flex>
            <Flex center sx={{ fontSize: 18 }}>
              {openCollapse ? <MdExpandLess /> : <MdExpandMore />}

              {isNested && compactMode && (
                <Flex center sx={{ marginLeft: 4 }}>
                  <MdChevronRight />
                </Flex>
              )}
            </Flex>
          </Flex>
        </ListButton>
      </CompactMenuTooltip>
      <MuiCollapse in={openCollapse} className="salvon-animate-height">
        <Entries
          isNested={true}
          items={items}
          compactMode={compactMode}
          hasPermissionTo={hasPermissionTo}
          sx={{ padding: '5px 0 0 7px' }}
        />
      </MuiCollapse>
    </Div>
  );
}
