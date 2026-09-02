export const writeTextToClipboard = async (data: string) => {
  if (!navigator || !navigator.clipboard) {
    console.log('Clipboard writing is not available.');
    return;
  }

  const clipboard = navigator.clipboard;
  await clipboard.writeText(data);
};

export const readTextFromClipboard = async (data: string) => {
  if (!navigator || !navigator.clipboard) {
    console.log('Clipboard writing is not available.');
    return '';
  }

  const clipboard = navigator.clipboard;
  return await clipboard.readText();
};
