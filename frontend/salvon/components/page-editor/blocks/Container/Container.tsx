import { DropZone } from '@puckeditor/core';

import { contentWidth } from '../_shared/padding';

import { Flex } from '@salvon/components/div';

const maxWidths: Record<
  ContainerProps['maxWidth'],
  string | Record<string, string>
> = {
  standard: contentWidth,
  narrow: { lg: '60%', xl: '50%' },
  wide: { lg: '100%', xl: '90%' },
  full: '100%',
};

export type ContainerProps = {
  maxWidth: 'standard' | 'narrow' | 'wide' | 'full';
  paddingX: number;
  paddingY: number;
};

export default function Container({
  maxWidth,
  paddingX,
  paddingY,
}: ContainerProps) {
  return (
    <Flex center>
      <Flex
        column
        sx={{
          px: `${paddingX ?? 1}px`,
          py: `${paddingY ?? 32}px`,
          width: '100%',
          maxWidth: maxWidths[maxWidth] ?? contentWidth,
        }}
      >
        <DropZone zone="content" />
      </Flex>
    </Flex>
  );
}
