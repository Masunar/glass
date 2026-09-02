import {
  readTextFromClipboard,
  writeTextToClipboard,
} from '@salvon/utils/clipboard';

export const useClipboard = () => {
  return { write: writeTextToClipboard, read: readTextFromClipboard };
};
