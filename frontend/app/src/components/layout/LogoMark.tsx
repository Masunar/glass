type Props = {
  size?: number;
  /** Domyślnie dziedziczy kolor tekstu z otoczenia. */
  color?: string;
  title?: string;
};

/**
 * Znak graficzny marki — cztery pola w układzie 2×2: dwa pełne
 * kwadraty i dwa trójkąty przecięte po przekątnej.
 *
 * Geometria przeniesiona bez zmian z oryginalnego pliku
 * (assets/svg/32/Logo.svg). Kolor wyprowadzony na `currentColor`,
 * żeby znak brał barwę z otoczenia zamiast mieć zaszyte #848484.
 *
 * SVG jest wstawiony w kod, a nie ładowany adresem — ekran logowania
 * nie może zależeć od dostępności zewnętrznego serwera.
 */
export default function LogoMark({ size = 34, color, title }: Props) {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      width={size}
      height={size}
      viewBox="0 0 32 32"
      role={title ? 'img' : undefined}
      aria-hidden={title ? undefined : true}
      aria-label={title}
      style={{ color, display: 'block', flexShrink: 0 }}
    >
      {title && <title>{title}</title>}
      <g fill="currentColor" fillRule="evenodd">
        <rect x="0" y="0" width="14.6285714" height="15.0857143" />
        <polygon points="17.3714286 0 32 15.0857143 17.3714286 15.0857143" />
        <rect
          x="17.3714286"
          y="16.9142857"
          width="14.6285714"
          height="15.0857143"
        />
        <polygon
          transform="translate(7.314286, 24.457143) rotate(-180.000000) translate(-7.314286, -24.457143)"
          points="0 16.9142857 14.6285714 32 0 32"
        />
      </g>
    </svg>
  );
}
