import type { ReactNode } from 'react';

import { AccentIcon, HeadingTypography } from '@salvon/components/accent';
import { Button } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import type {
  GeneratePathParams,
  GeneratePathUrl,
} from '@salvon/utils/generate-path';

export type HeadingProps = {
  title?: ReactNode;
  children?: ReactNode;
  icon?: ReactNode;
  returnTo?: {
    path: GeneratePathUrl;
    pathParams?: GeneratePathParams;
  };
};

export default function Heading({
  title,
  icon,
  children,
  returnTo,
}: HeadingProps) {
  return (
    <Flex gap={1} wrap justify="space-between" align="center">
      <Flex gap={1} align="center">
        {icon && <AccentIcon>{icon}</AccentIcon>}
        <HeadingTypography>{title}</HeadingTypography>
      </Flex>
      <Flex gap={1} wrap align="center">
        {returnTo && (
          <Button
            preset="return"
            path={returnTo.path}
            pathParams={returnTo.pathParams}
          />
        )}
        {children}
      </Flex>
    </Flex>
  );
}
