import { Button, type ButtonProps } from './index';

export default function Submit(props: Omit<ButtonProps, 'type'>) {
  return <Button {...props} type="submit" />;
}
