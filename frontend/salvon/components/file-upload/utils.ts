import type { DropzoneTranslations } from './types';
import type { Accept } from 'react-dropzone';

import { formatFileSize } from '@salvon/utils/file';
import { isArray } from '@salvon/utils/type-check';

export const DEFAULT_TRANSLATIONS: DropzoneTranslations = {
  prompt: 'Przeciągnij i upuść plik tutaj lub ',
  browse: 'wybierz z dysku',
  reject: 'Nieobsługiwany format pliku',
  tooLarge: 'Plik jest za duży',
  formats: (exts) => `Obsługiwane formaty: ${exts.join(', ')}`,
  maxSize: (bytes) => `Maks.: ${formatFileSize(bytes)}`,
};

export const MIME_LABELS: Record<string, string> = {
  'application/vnd.ms-excel': 'XLS',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'XLSX',
  'application/vnd.ms-powerpoint': 'PPT',
  'application/vnd.openxmlformats-officedocument.presentationml.presentation':
    'PPTX',
  'application/msword': 'DOC',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
    'DOCX',
  'application/pdf': 'PDF',
  'text/csv': 'CSV',
  'text/plain': 'TXT',
  'application/zip': 'ZIP',
  'image/png': 'PNG',
  'image/jpeg': 'JPG',
  'image/webp': 'WEBP',
  'image/gif': 'GIF',
  'image/svg+xml': 'SVG',
};

const EXT_MIME: Record<string, string> = {
  '.xls': 'application/vnd.ms-excel',
  '.xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  '.ppt': 'application/vnd.ms-powerpoint',
  '.pptx':
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
  '.doc': 'application/msword',
  '.docx':
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  '.pdf': 'application/pdf',
  '.csv': 'text/csv',
  '.txt': 'text/plain',
  '.zip': 'application/zip',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.gif': 'image/gif',
  '.svg': 'image/svg+xml',
};

function labelForEntry(entry: string, labels: Record<string, string>): string {
  if (entry.startsWith('.')) {
    return entry.slice(1).toUpperCase();
  }
  if (labels[entry]) {
    return labels[entry];
  }
  if (entry.includes('/')) {
    return entry.split('/')[1].toUpperCase();
  }
  return entry.toUpperCase();
}

export function deriveExtensions(
  accept?: Accept | string[],
  mimeLabels?: Record<string, string>,
): string[] {
  if (!accept) {
    return [];
  }

  const labels = mimeLabels ? { ...MIME_LABELS, ...mimeLabels } : MIME_LABELS;

  const raw = isArray(accept)
    ? accept
    : Object.entries(accept).flatMap(([mime, exts]) =>
        exts.length ? exts : [mime],
      );

  return [...new Set(raw.filter(Boolean).map((e) => labelForEntry(e, labels)))];
}

export function normalizeAccept(
  accept?: Accept | string[],
): Accept | undefined {
  if (!isArray(accept)) {
    return accept;
  }

  return accept.reduce((acc: Record<string, string[]>, curr) => {
    if (curr.startsWith('.')) {
      const mime = EXT_MIME[curr.toLowerCase()] ?? curr;
      (acc[mime] ??= []).push(curr);
    } else {
      acc[curr] ??= [];
    }
    return acc;
  }, {});
}
