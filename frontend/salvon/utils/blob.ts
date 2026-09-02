import type { ResponseProps } from '@salvon/request';

export const downloadBlob = (blob: Blob, filename: string) => {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
};

export const downloadBlobFromResponse = (
  response: ResponseProps<Blob>,
  fallbackFilename: string,
) => {
  const headers = response.response.headers;
  const contentDisposition = headers['content-disposition'];
  let filename = fallbackFilename;

  if (contentDisposition) {
    filename = contentDisposition.split('filename=')[1].split(';')[0];
  }

  downloadBlob(response.content, filename || fallbackFilename);
};
