export const fileToBase64 = async (file: File) => {
  const reader = new FileReader();
  reader.readAsDataURL(file);

  return await new Promise<string>((resolve) => {
    reader.onload = () => resolve(reader.result as string);
  });
};

export const fileToUploadableBase64 = async (file: File) => {
  const { name, extension } = fileSplitName(file.name);
  return {
    filename: file.name,
    content: await fileToBase64(file),
    name,
    extension,
    mime: file.type,
  };
};

export const formatFileSize = (bytes: number) => {
  if (bytes < 1024) return `${bytes} B`;
  const units = ['KB', 'MB', 'GB', 'TB'];
  let size = bytes / 1024;
  let unit = 0;
  while (size >= 1024 && unit < units.length - 1) {
    size /= 1024;
    unit++;
  }
  return `${size % 1 === 0 ? size : size.toFixed(1)} ${units[unit]}`;
};

export const fileSplitName = (filename: string) => {
  const lastDotIndex = filename.lastIndexOf('.');

  if (lastDotIndex === -1) {
    return { name: filename, extension: '' };
  }

  return {
    name: filename.substring(0, lastDotIndex),
    extension: filename.substring(lastDotIndex + 1),
  };
};
