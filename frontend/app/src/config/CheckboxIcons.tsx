type IconProps = {
  fill: string;
  borderColor: string;
};

export function CheckboxIcon({ borderColor }: Pick<IconProps, 'borderColor'>) {
  return (
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
      <rect
        x="1.5"
        y="1.5"
        width="17"
        height="17"
        rx="5"
        fill="transparent"
        stroke={borderColor}
        strokeWidth="1.5"
      />
    </svg>
  );
}

export function CheckboxCheckedIcon({ fill }: Pick<IconProps, 'fill'>) {
  return (
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
      <rect x="1" y="1" width="18" height="18" rx="5" fill={fill} />
      <path
        d="M6 10.2l2.6 2.6L14.2 7"
        stroke="#ffffff"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

export function CheckboxIndeterminateIcon({ fill }: Pick<IconProps, 'fill'>) {
  return (
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
      <rect x="1" y="1" width="18" height="18" rx="5" fill={fill} />
      <path
        d="M6 10h8"
        stroke="#ffffff"
        strokeWidth="2"
        strokeLinecap="round"
      />
    </svg>
  );
}

export function RadioIcon({ borderColor }: Pick<IconProps, 'borderColor'>) {
  return (
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
      <circle
        cx="10"
        cy="10"
        r="8.25"
        fill="transparent"
        stroke={borderColor}
        strokeWidth="1.5"
      />
    </svg>
  );
}

export function RadioCheckedIcon({ fill }: Pick<IconProps, 'fill'>) {
  return (
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
      <circle cx="10" cy="10" r="9" fill={fill} />
      <circle cx="10" cy="10" r="3.5" fill="#ffffff" />
    </svg>
  );
}
