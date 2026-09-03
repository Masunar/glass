import type { HTMLInputTypeAttribute } from 'react';

import { useCurrentForm } from '@salvon/hooks/useForm';

type Props = {
  name: string;
  label: string;
  required?: boolean;
  placeholder?: string;
  /** `num` dla kwot i liczb, `key` dla NIP-u — cyfry w kroju nagłówkowym. */
  emphasis?: 'num' | 'key';
  type?: HTMLInputTypeAttribute;
  style?: React.CSSProperties;
};

/**
 * Pole jako podkreślenie, nie ramka.
 *
 * W tym systemie nie ma kafelków ani obwódek — ramka wokół każdego pola
 * dokłada tyle linii, ile jest pól, a formularz kontrahenta ma ich
 * kilkanaście.
 */
export default function Field({
  name,
  label,
  required,
  placeholder,
  emphasis,
  type = 'text',
  style,
}: Props) {
  const { register, formState } = useCurrentForm();
  const error = formState.errors[name];
  const message = typeof error?.message === 'string' ? error.message : null;

  return (
    <label
      className={message !== null ? 'ge-uf is-invalid' : 'ge-uf'}
      style={style}
    >
      <span className="ge-uf__label">
        {label}
        {required && <span className="ge-uf__req"> •</span>}
      </span>
      <input
        {...register(name)}
        type={type}
        placeholder={placeholder ?? '—'}
        className={
          emphasis ? `ge-uf__input ge-uf__input--${emphasis}` : 'ge-uf__input'
        }
        aria-invalid={message !== null}
      />
      {message !== null && <span className="ge-uf__error">{message}</span>}
    </label>
  );
}

export function Toggle({ name, label }: { name: string; label: string }) {
  const { register } = useCurrentForm();

  return (
    <label className="ge-toggle">
      <input type="checkbox" {...register(name)} />
      <span className="ge-toggle__track" />
      {label}
    </label>
  );
}
