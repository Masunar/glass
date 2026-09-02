import type { ReactNode } from 'react';
import { Toaster, type ToasterProps } from 'sonner';

export type NotificationProviderProps = ToasterProps & {
  children: ReactNode;
};

export default function NotificationProvider({
  children,
  position = 'top-right',
  ...props
}: NotificationProviderProps) {
  return (
    <>
      <Toaster position={position} {...props} />
      {children}
    </>
  );
}
