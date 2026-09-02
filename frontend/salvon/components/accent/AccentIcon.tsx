import type { SxProps } from '@mui/material';

import { Flex, type FlexProps } from '@salvon/components/div';
import { Link, type LinkProps } from '@salvon/components/navigation';
import { usePalette } from '@salvon/hooks/useTheme';
import type { SlotItem } from '@salvon/types';
import {
  type GeneratePathParams,
  type GeneratePathUrl,
} from '@salvon/utils/generate-path';

export type AccentIconProps = FlexProps & {
  path?: GeneratePathUrl;
  pathParams?: GeneratePathParams;
  slotProps?: {
    link?: SlotItem<LinkProps>;
  };
  sx?: SxProps;
};

export default function AccentIcon({
  path,
  slotProps,
  pathParams = {},
  sx = {},
  ...props
}: AccentIconProps) {
  const palette = usePalette();
  const { link } = slotProps ?? {};

  let element = (
    <Flex
      center
      sx={{
        width: 36,
        height: 36,
        borderRadius: 1,
        fontSize: 22,
        backgroundColor: 'action.hover',
        ...(palette.salvon?.accent_icon ?? {}),
        ...((sx ?? {}) as any),
      }}
      {...props}
    />
  );

  if (path) {
    element = (
      <Link {...link} path={path} pathParams={pathParams}>
        {element}
      </Link>
    );
  }

  return element;
}
