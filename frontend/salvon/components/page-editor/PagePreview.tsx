import { Render } from '@puckeditor/core';

import { defaultPuckConfig } from './blocks';
import type { PagePreviewProps } from './types.d';

import { Div } from '@salvon/components/div';

export default function PagePreview({
  config = defaultPuckConfig,
  value,
  slotProps,
}: PagePreviewProps) {
  return (
    <Div {...slotProps?.root} sx={{ ...slotProps?.root?.sx }}>
      <Render config={config} data={value} />
    </Div>
  );
}
