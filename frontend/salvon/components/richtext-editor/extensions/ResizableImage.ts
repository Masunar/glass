import Image from '@tiptap/extension-image';
import { ReactNodeViewRenderer } from '@tiptap/react';

import ResizableImageView from './ResizableImageView';

export const ResizableImage = Image.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      width: {
        default: null,
        parseHTML: (element) =>
          element.style.width || element.getAttribute('width') || null,
        renderHTML: (attributes) => {
          if (!attributes.width) return {};
          const w = String(attributes.width);
          const value = /^\d+$/.test(w) ? `${w}px` : w;
          return { style: `width: ${value}` };
        },
      },
    };
  },
  addNodeView() {
    return ReactNodeViewRenderer(ResizableImageView);
  },
});

export default ResizableImage;
