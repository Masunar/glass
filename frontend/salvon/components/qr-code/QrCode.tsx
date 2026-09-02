import { QRCodeSVG } from 'qrcode.react';
import type { ComponentProps } from 'react';

export type QrCodeProps = Omit<ComponentProps<typeof QRCodeSVG>, 'value'> & {
  value: string;
  size?: number;
};

export default function QrCode(props: QrCodeProps) {
  return <QRCodeSVG {...props} />;
}
