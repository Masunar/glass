import TaskDrawerField from './TaskDrawerField';
import type { TaskDrawerField as FieldConfig } from './types.d';

import { Flex } from '@salvon/components/div';
import type { FlexProps } from '@salvon/components/div';
import { useIsDarkMode } from '@salvon/hooks/useTheme';
import type { SlotItem } from '@salvon/types';

type Props = {
  fields: FieldConfig[];
  slotProps?: SlotItem<FlexProps>;
};

export default function TaskDrawerFields({ fields, slotProps }: Props) {
  const isDark = useIsDarkMode();
  const { sx, ...rest } = slotProps ?? {};

  return (
    <Flex
      column
      fw
      {...rest}
      sx={{
        minWidth: 0,
        borderRadius: '12px',
        overflow: 'hidden',
        border: '1px solid',
        borderColor: isDark ? 'divider' : '#e7ebf1',
        backgroundColor: isDark ? 'transparent' : '#fff',
        px: 2,
        py: 0.5,
        '& > *': {
          borderBottom: '1px solid',
          borderColor: isDark ? 'divider' : '#f4f6f9',
        },
        '& > *:last-of-type': { borderBottom: 'none' },
        ...sx,
      }}
    >
      {fields.map((field) => (
        <TaskDrawerField key={field.key} field={field} />
      ))}
    </Flex>
  );
}
