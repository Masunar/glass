import type { StatefulFile } from '../types';
import FileRow from './FileRow';

import { Flex } from '@salvon/components/div';

export type FileListProps = {
  files: StatefulFile[];
  onRemove: (index: number) => void;
};

export default function FileList({ files, onRemove }: FileListProps) {
  if (files.length === 0) {
    return null;
  }

  return (
    <Flex mt={1.5} column gap={1}>
      {files.map((f, i) => (
        <FileRow key={i} file={f} onRemove={() => onRemove(i)} />
      ))}
    </Flex>
  );
}
