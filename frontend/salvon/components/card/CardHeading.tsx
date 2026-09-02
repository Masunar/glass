import { Typography } from '@mui/material';

import { type ReactNode } from 'react';

import { AccentIcon } from '@salvon/components/accent';
import { Flex } from '@salvon/components/div';

export type CardHeadingProps = {
  icon?: ReactNode;
  title: ReactNode;
  subtitle?: ReactNode;
  end?: ReactNode;
};

export default function CardHeading({
  icon,
  title,
  subtitle,
  end,
}: CardHeadingProps) {
  return (
    <Flex aCenter gap={1.5}>
      {icon && <AccentIcon>{icon}</AccentIcon>}
      <Flex column sx={{ minWidth: 0, gap: '2px' }}>
        <Typography sx={{ fontWeight: 700, lineHeight: 1.25 }}>
          {title}
        </Typography>
        {subtitle && (
          <Typography
            variant="body2"
            sx={{ color: 'text.secondary', lineHeight: 1.3 }}
          >
            {subtitle}
          </Typography>
        )}
      </Flex>
      {end && <Flex sx={{ ml: 'auto' }}>{end}</Flex>}
    </Flex>
  );
}
