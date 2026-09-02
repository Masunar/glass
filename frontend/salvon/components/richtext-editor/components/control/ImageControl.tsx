import { Popover, TextField } from '@mui/material';
import type { Editor } from '@tiptap/react';

import ToolbarButton from './ToolbarButton';
import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent, MouseEvent } from 'react';
import { MdImage } from 'react-icons/md';

import { Button } from '@salvon/components/button';
import { Div, Flex } from '@salvon/components/div';

const MAX_IMAGE_DIMENSION = 800;
const JPEG_QUALITY = 0.7;

const fileToDataUrl = (file: File): Promise<string> =>
  new Promise((resolve, reject) => {
    const objectUrl = URL.createObjectURL(file);
    const img = new Image();

    img.onload = () => {
      URL.revokeObjectURL(objectUrl);

      let { width, height } = img;
      if (width > MAX_IMAGE_DIMENSION || height > MAX_IMAGE_DIMENSION) {
        if (width >= height) {
          height = Math.round((height * MAX_IMAGE_DIMENSION) / width);
          width = MAX_IMAGE_DIMENSION;
        } else {
          width = Math.round((width * MAX_IMAGE_DIMENSION) / height);
          height = MAX_IMAGE_DIMENSION;
        }
      }

      const canvas = document.createElement('canvas');
      canvas.width = width;
      canvas.height = height;
      const ctx = canvas.getContext('2d');
      if (!ctx) {
        reject(new Error('canvas not supported'));
        return;
      }

      ctx.drawImage(img, 0, 0, width, height);
      resolve(canvas.toDataURL('image/jpeg', JPEG_QUALITY));
    };

    img.onerror = () => {
      URL.revokeObjectURL(objectUrl);
      reject(new Error('failed to load image'));
    };

    img.src = objectUrl;
  });

type ImageControlProps = {
  editor: Editor;
  disabled: boolean;
  label: string;
  urlPlaceholder: string;
  insertLabel: string;
  uploadLabel: string;
};

export function ImageControl({
  editor,
  disabled,
  label,
  urlPlaceholder,
  insertLabel,
  uploadLabel,
}: ImageControlProps) {
  const anchorRef = useRef<HTMLSpanElement>(null);
  const fileRef = useRef<HTMLInputElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const [open, setOpen] = useState(false);
  const [url, setUrl] = useState('');

  useEffect(() => {
    if (open) requestAnimationFrame(() => inputRef.current?.focus());
  }, [open]);

  const insertUrl = () => {
    const src = url.trim();
    if (!src) return;
    editor.chain().focus().setImage({ src }).run();
    setUrl('');
    setOpen(false);
  };

  const onFile = async (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    const src = await fileToDataUrl(file).catch(() => null);
    if (src) editor.chain().focus().setImage({ src }).run();
    setOpen(false);
  };

  return (
    <>
      <span ref={anchorRef}>
        <ToolbarButton
          label={label}
          disabled={disabled}
          onClick={() => setOpen(true)}
        >
          <MdImage />
        </ToolbarButton>
      </span>
      <Popover
        open={open}
        anchorEl={anchorRef.current}
        onClose={() => setOpen(false)}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'left' }}
        disableAutoFocus
        disableEnforceFocus
        disableRestoreFocus
      >
        <Div sx={{ p: 1, width: 320 }}>
          <Flex sx={{ gap: '8px', alignItems: 'center' }}>
            <TextField
              inputRef={inputRef}
              size="small"
              fullWidth
              placeholder={urlPlaceholder}
              value={url}
              onChange={(e) => setUrl(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  insertUrl();
                }
              }}
            />
            <Button
              size="small"
              onMouseDown={(e: MouseEvent) => e.preventDefault()}
              onClick={insertUrl}
            >
              {insertLabel}
            </Button>
          </Flex>
          <Button
            variant="outlined"
            size="small"
            fullWidth
            sx={{ mt: 1 }}
            onClick={() => fileRef.current?.click()}
          >
            {uploadLabel}
          </Button>
          <input
            ref={fileRef}
            type="file"
            accept="image/*"
            onChange={onFile}
            style={{ display: 'none' }}
          />
        </Div>
      </Popover>
    </>
  );
}
