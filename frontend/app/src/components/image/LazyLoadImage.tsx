import { CircularProgress, type SxProps } from '@mui/material';
import Typography from '@mui/material/Typography';

import NoPhotos from './NoPhotos';
import { photoVariants } from './imageUtils';
import { useEffect, useState } from 'react';
import { CiImageOff } from 'react-icons/ci';

import { Flex } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';

import VariantableImage from '@app/components/image/VariantableImage';

interface Props {
  src: string;
  alt: string;
  disableAnimation?: boolean;
  disableText?: boolean;
  sx?: SxProps;
  width?: string;
  height?: string;
  variant?: string;
  isLoading?: boolean;
  imgProps?: any;
  contain?: boolean;
  noImage?: boolean;
  noImageProps?: any;
}

export default function LazyLoadImage({
  src,
  alt,
  disableAnimation,
  disableText,
  sx,
  width = '200px',
  height = '160px',
  variant = photoVariants.PUBLIC,
  isLoading = false,
  imgProps,
  contain = false,
  noImage = false,
  noImageProps,
  ...props
}: Props) {
  const t = useTranslation();
  const palette = usePalette();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [imageUrl, setImageUrl] = useState('');

  useEffect(() => {
    if (imageUrl) {
      const img = new Image();
      img.src = imageUrl;
      img.onload = () => {
        setLoading(false);
        setError(false);
      };
      img.onerror = () => {
        setError(true);
        setLoading(false);
      };
    }
  }, [imageUrl]);

  useEffect(() => {
    if (!src && !isLoading) {
      setLoading(false);
      setError(true);
      return;
    }
  }, [src]);

  return (
    <Flex
      sx={{
        position: 'relative',
        width: width,
        height: height,
        overflow: 'hidden',
        borderRadius: '3px',
        ...sx,
      }}
      center
      {...props}
    >
      {loading && (
        <Flex
          sx={{
            zIndex: 1,
            position: 'absolute',
            left: '50%',
            top: '50%',
            transform: 'translate(-50%, -50%)',
          }}
        >
          <CircularProgress />
        </Flex>
      )}
      {error && !noImage && (
        <Flex
          column
          center
          sx={{
            width,
            height,
            position: 'absolute',
            top: '50%',
            left: '50%',
            transform: 'translate(-50%, -50%)',
            gap: 1,
          }}
        >
          <CiImageOff
            //@ts-ignore
            color={palette.primary?.main}
            size={30}
          />
          {!disableText && (
            <Typography sx={{ fontSize: 11, textAlign: 'center' }}>
              {t('cannot_load_image')}
            </Typography>
          )}
        </Flex>
      )}
      {noImage && <NoPhotos {...noImageProps} />}
      {src && (
        <VariantableImage
          src={src}
          alt={alt}
          cover={!contain}
          variant={variant}
          onLoad={() => {
            setLoading(false);
            setError(false);
          }}
          onError={() => {
            setError(true);
            setLoading(false);
          }}
          handleReturnUrl={setImageUrl}
          {...imgProps}
          sx={{
            opacity: loading || error ? 0 : 1,
            transition: disableAnimation
              ? 'none !important'
              : '0.2s !important',
            height: '100%',
            width: '100%',
            // m: '1px',
            ...imgProps?.sx,
          }}
        />
      )}
    </Flex>
  );
}
