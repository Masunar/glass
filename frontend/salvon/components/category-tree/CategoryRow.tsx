import { Typography } from '@mui/material';

import type { ReactNode } from 'react';
import { PiCaretDown, PiCaretUp, PiFolder, PiFolderOpen } from 'react-icons/pi';

import { Flex } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';

export const ROW_HEIGHT = 40;

export type CategoryRowProps = {
  name: string;
  isActive: boolean;
  level: number;
  indent: number;
  hasChildren: boolean;
  isOpen: boolean;
  noName: string;
  actions?: ReactNode;
  onToggle: () => void;
};

export default function CategoryRow({
  name,
  isActive,
  level,
  indent,
  hasChildren,
  isOpen,
  noName,
  actions,
  onToggle,
}: CategoryRowProps) {
  const palette = usePalette();

  return (
    <Flex
      aCenter
      gap={1}
      onClick={onToggle}
      sx={{
        height: ROW_HEIGHT,
        paddingLeft: `${indent}px`,
        pr: 1.5,
        cursor: 'pointer',
        borderBottom: `1px solid ${palette.divider}`,
        transition: 'background-color 0.1s',
        '&:hover': { backgroundColor: 'action.hover' },
        '&:hover .ct-actions': { opacity: 1 },
      }}
    >
      <Flex aCenter sx={{ width: 18, flexShrink: 0 }}>
        {hasChildren &&
          (isOpen ? <PiCaretUp size={14} /> : <PiCaretDown size={14} />)}
      </Flex>

      <Flex
        aCenter
        sx={{ flexShrink: 0, fontSize: 18, opacity: hasChildren ? 1 : 0.5 }}
      >
        {hasChildren && isOpen ? <PiFolderOpen /> : <PiFolder />}
      </Flex>

      <Typography
        sx={{
          flex: 1,
          fontSize: level === 0 ? 14 : 13,
          fontWeight: level === 0 ? 600 : level === 1 ? 500 : 400,
          opacity: isActive ? 1 : 0.4,
          fontStyle: !name ? 'italic' : undefined,
          color: !name ? 'text.secondary' : undefined,
        }}
      >
        {name || noName}
      </Typography>

      {actions && (
        <Flex
          className="ct-actions"
          aCenter
          gap={0.5}
          sx={{ flexShrink: 0, opacity: 0.3, transition: 'opacity 0.15s' }}
          onClick={(e) => e.stopPropagation()}
        >
          {actions}
        </Flex>
      )}
    </Flex>
  );
}
