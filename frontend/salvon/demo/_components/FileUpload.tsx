import GroupLabel from './GroupLabel';
import { MdCloudUpload } from 'react-icons/md';

import { BaseButton } from '@salvon/components/button';
import { Card } from '@salvon/components/card';
import { Flex } from '@salvon/components/div';
import { FileUpload as Dropzone } from '@salvon/components/file-upload';
import { notifyError } from '@salvon/utils/notify';

const dropzoneWrapper = {
  sx: (theme: any) => ({
    border: `2px dashed ${theme.palette.mode === 'dark' ? '#3a3a3a' : '#c7d0e0'} !important`,
    borderRadius: '10px !important',
    padding: '32px 10px !important',
    backgroundColor:
      theme.palette.mode === 'dark' ? 'rgba(255,255,255,0.02)' : '#fafbfd',
  }),
};

type Props = {};
export default function FileUpload({}: Props) {
  return (
    <Card
      fw
      heading={{
        icon: <MdCloudUpload />,
        title: 'Upload',
        subtitle: 'Strefy przeciągnij-i-upuść — pojedynczy i wielokrotny plik',
      }}
    >
      <Flex gap={3} fw wrap>
        <Flex column gap={1} sx={{ flex: '1 1 260px', minWidth: 260 }}>
          <GroupLabel>Multiple</GroupLabel>
          <Dropzone
            multiple
            maxSize={1 * 1024 * 1024}
            accept={['.png', '.xlsx', '.xls']}
            onChange={(f) => console.log(f)}
          />
        </Flex>
        <Flex column gap={1} sx={{ flex: '1 1 260px', minWidth: 260 }}>
          <GroupLabel>Multiple</GroupLabel>
          <Dropzone
            multiple
            maxSize={100 * 1024 * 1024}
            accept={['.xlsx', '.xls']}
            onChange={(f) => console.log(f)}
          />
        </Flex>
        <Flex column gap={1} sx={{ flex: '1 1 260px', minWidth: 260 }}>
          <GroupLabel>Single</GroupLabel>
          <Dropzone
            slotProps={{ wrapper: dropzoneWrapper }}
            onChange={(f) => console.log(f)}
            accept={['image/jpeg', 'image/png']}
          />
        </Flex>
        <Flex column gap={1} sx={{ flex: '1 1 100%' }}>
          <GroupLabel>Compact</GroupLabel>
          <Dropzone
            variant="compact"
            multiple
            maxSize={1 * 1024 * 1024}
            accept={['.png', '.jpg', '.pdf', '.xlsx']}
            onChange={(f) => console.log(f)}
            onFileTooLarge={(files) =>
              notifyError(`Plik "${files[0].name}" jest za duży`)
            }
          >
            <BaseButton
              variant="outlined"
              sx={{
                borderColor: '#7e7e7e',
              }}
            >
              Wybierz
            </BaseButton>
          </Dropzone>
        </Flex>
      </Flex>
    </Card>
  );
}
