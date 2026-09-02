import { type SxProps } from '@mui/material';
import Typography from '@mui/material/Typography';

import { MdOutlineInsertPhoto } from 'react-icons/md';

import { Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

interface Props {
  sx?: SxProps;
  scale?: number;
  customLabel?: string;
}

export default function NoPhotos({ sx, scale = 1, customLabel }: Props) {
  const t = useTranslation();

  return (
    <Flex center column fh sx={{ opacity: 0.6, ...sx }}>
      <MdOutlineInsertPhoto size={35 * scale} />
      <Typography sx={{ fontSize: 14 * scale }}>
        {customLabel || t('no_photos')}
      </Typography>
    </Flex>
  );
}
