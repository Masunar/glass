import { CircularProgress, Collapse, Typography } from '@mui/material';

import type { TaskDrawerSectionProps } from './types.d';
import { useState } from 'react';
import { MdExpandMore } from 'react-icons/md';

import { Div, Flex } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';
import { useIsDarkMode } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function TaskDrawerSection({
  title,
  icon,
  defaultOpen = true,
  loading = false,
  action,
  children,
  open,
  setOpen,
  slotProps,
}: TaskDrawerSectionProps) {
  const t = useTranslation();
  const isDark = useIsDarkMode();

  const [internalOpen, setInternalOpen] = useState(defaultOpen);
  const isOpen = open ?? internalOpen;
  const toggle = () => {
    const next = !isOpen;
    setOpen?.(next);
    if (open === undefined) setInternalOpen(next);
  };

  const { sx: headerSx, ...headerRest } = slotProps?.header ?? {};
  const { sx: bodySx, ...bodyRest } = slotProps?.body ?? {};

  return (
    <Div fw>
      <Flex
        aCenter
        onClick={toggle}
        {...headerRest}
        sx={{
          gap: 0.5,
          cursor: 'pointer',
          borderRadius: 2,
          '&:hover': { background: isDark ? '#222' : '#eee' },
          ...headerSx,
        }}
      >
        <IconButton
          variant="mui"
          icon={
            loading ? (
              <CircularProgress size="1em" />
            ) : (
              <MdExpandMore size={20} />
            )
          }
          label={isOpen ? t('collapse') : t('expand')}
          disabled={loading}
          disableRipple
          onClick={(event) => {
            event.stopPropagation();
            toggle();
          }}
          sx={{
            transform: isOpen ? 'rotate(180deg)' : 'rotate(0deg)',
            transition: 'transform 0.2s',
          }}
        />
        {icon && (
          <Flex aCenter sx={{ opacity: 0.55, '& svg': { display: 'block' } }}>
            {icon}
          </Flex>
        )}
        <Typography sx={{ fontSize: 14 }}>{title}</Typography>
        {action && <Div sx={{ ml: 'auto' }}>{action}</Div>}
      </Flex>

      <Collapse in={isOpen} unmountOnExit>
        <Flex column fw {...bodyRest} sx={{ px: 1, py: 1, ...bodySx }}>
          {children}
        </Flex>
      </Collapse>
    </Div>
  );
}
