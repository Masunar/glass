import type { FileUploadProps } from './types';
import CompactDropzone from './variant/CompactDropzone';
import StandardDropzone from './variant/StandardDropzone';
import { createElement } from 'react';

export default function FileUpload({
  variant = 'standard',
  ...props
}: FileUploadProps) {
  switch (variant) {
    case 'compact':
      return createElement(CompactDropzone, props);
    default:
      return createElement(StandardDropzone, props);
  }
}
