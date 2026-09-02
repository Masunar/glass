import { type RbcEvent } from '../internal';

import { Flex } from '@salvon/components/div';

export type EventChipProps<T> = {
  event: RbcEvent<T>;
};

export default function EventChip<T>({ event }: EventChipProps<T>) {
  return (
    <Flex
      aCenter
      sx={{
        gap: 0.75,
        px: 0.75,
        py: 0.25,
        minWidth: 0,
        color: 'inherit',
      }}
    >
      <Flex
        sx={{
          width: 6,
          height: 6,
          borderRadius: '50%',
          flexShrink: 0,
          backgroundColor: 'currentColor',
        }}
      />
      <Flex
        component="span"
        sx={{
          minWidth: 0,
          overflow: 'hidden',
          textOverflow: 'ellipsis',
          whiteSpace: 'nowrap',
          fontWeight: 500,
        }}
      >
        {event.title}
      </Flex>
    </Flex>
  );
}
