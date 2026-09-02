import { styled } from '@mui/material';

import type { CSSProperties, ImgHTMLAttributes } from 'react';

export type ImgProps = ImgHTMLAttributes<HTMLImageElement> & {
  fallbackImage?: string;
  alt?: string;
  sx?: CSSProperties;
};
export default function Img({ fallbackImage, alt, sx, ...props }: ImgProps) {
  const Image = styled('img')(sx ?? {});

  return (
    <Image
      {...props}
      alt={alt}
      onError={(e) => {
        if (fallbackImage) {
          //@ts-ignore
          e.target.src = fallbackImage;
        }
      }}
    />
  );
}
