import type { StatefulFile } from '../types';
import FilePill from './FilePill';

import { Flex } from '@salvon/components/div';

export type FilePillListProps = {
  files: StatefulFile[];
  onRemove: (index: number) => void;
};

export default function FilePillList({ files, onRemove }: FilePillListProps) {
  if (files.length === 0) {
    return null;
  }

  return (
    <Flex mt={1.5} wrap gap={1}>
      {files.map((f, i) => (
        <FilePill key={i} file={f} onRemove={() => onRemove(i)} />
      ))}
    </Flex>
  );
}
