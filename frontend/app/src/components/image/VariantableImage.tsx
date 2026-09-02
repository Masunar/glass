import { type SxProps } from '@mui/material';

import { addVariantToUrl, photoVariants } from './imageUtils';
import { type ImgHTMLAttributes } from 'react';

import { Div } from '@salvon/components/div';

interface Props {
  src?: string;
  alt?: string;
  sx?: SxProps;
  cover?: boolean;
  lazyLoading?: boolean;
  useAsBackground?: boolean;
  variant?: string;
  mimeType?: string;
  handleReturnUrl?: (url: string) => void;
  disableVariant?: boolean;
  overwriteSrcSets?: boolean;
  srcSets?: string;
  overwriteVariant?: boolean;
}

export default function VariantableImage({
  src,
  alt,
  sx,
  cover,
  lazyLoading,
  useAsBackground,
  variant = photoVariants.PUBLIC,
  mimeType = 'image/png',
  handleReturnUrl,
  disableVariant,
  overwriteSrcSets,
  srcSets,
  overwriteVariant = false,
  ...props
}: Props & ImgHTMLAttributes<HTMLImageElement>) {
  const isBase64 = (str: string) => {
    const base64Pattern = /^[A-Za-z0-9+/=]+$/;
    return base64Pattern.test(str);
  };

  const addBase64Prefix = (str: string) => {
    return `data:${mimeType};base64,${str}`;
  };

  if (!src) {
    return <Div sx={{ width: '100%', height: '100%', ...sx }} />;
  }

  const isBase64Image = isBase64(src);
  const imageUrl = isBase64Image
    ? addBase64Prefix(src)
    : disableVariant
      ? src
      : addVariantToUrl(src, variant, overwriteVariant);

  if (handleReturnUrl) {
    handleReturnUrl(imageUrl);
  }

  const srcSet =
    !isBase64Image && !disableVariant
      ? srcSets
        ? srcSets
        : `${addVariantToUrl(src, overwriteSrcSets ? variant : photoVariants.SMALL, overwriteVariant)} 480w, ${addVariantToUrl(src, overwriteSrcSets ? variant : photoVariants.BG, overwriteVariant)} 800w, ${addVariantToUrl(src, overwriteSrcSets ? variant : photoVariants.PUBLIC, overwriteVariant)} 1200w`
      : undefined;

  if (useAsBackground) {
    return (
      <Div
        sx={{
          backgroundImage: `url(${imageUrl})`,
          backgroundSize: cover ? 'cover' : 'contain',
          backgroundPosition: 'center',
          backgroundRepeat: 'no-repeat',
          width: '100%',
          height: '100%',
          ...sx,
        }}
        {...props}
      />
    );
  }

  return (
    <Div
      component="img"
      //@ts-ignore
      src={imageUrl}
      srcSet={srcSet}
      alt={alt}
      sx={{
        width: '100%',
        height: '100%',
        objectFit: cover ? 'cover' : 'contain',
        ...sx,
      }}
      loading={lazyLoading ? 'lazy' : 'eager'}
      {...props}
    />
  );
}
