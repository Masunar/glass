import { type ButtonProps, Button as MUIButton, Tooltip } from '@mui/material';
import { styled } from '@mui/material/styles';

import { createElement } from 'react';
import type { IconBaseProps } from 'react-icons';
import { HiLanguage } from 'react-icons/hi2';

import { Div } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';
import { Popover, type PopoverProps } from '@salvon/components/popover';
import { useLocale } from '@salvon/hooks/useLocale';
import { usePalette } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';
import type { CloneableElement, Locales, SlotItem } from '@salvon/types';
import { isFunction } from '@salvon/utils/type-check';

export type ChangeLanguageProps = {
  closeOnChange?: boolean;
  locales: Locales;
  anchor?: CloneableElement;
  slotProps?: {
    icon: SlotItem<IconBaseProps>;
    button: SlotItem<ButtonProps>;
    popover: SlotItem<PopoverProps>;
  };
};

export default function ChangeLanguage({
  anchor,
  locales,
  slotProps,
  closeOnChange = true,
}: ChangeLanguageProps) {
  const { icon, button, popover } = slotProps ?? {};

  const t = useTranslation();
  const { locale, setLocale } = useLocale();

  if (!anchor) {
    anchor = (
      <Tooltip title={t('change_language')}>
        <IconButton>
          <HiLanguage {...icon} />
        </IconButton>
      </Tooltip>
    );
  }

  return (
    <Div>
      <Popover {...popover} anchor={anchor}>
        {({ closePopover }) => (
          <div style={{ padding: '5px', maxWidth: 200 }}>
            {locales.map((item, index) => {
              let flag: any = item.flag;
              if (isFunction(flag)) {
                flag = createElement(flag);
              }

              return (
                <LocaleButton
                  {...button}
                  key={item.name}
                  className={locale === item.identifier ? 'active' : ''}
                  onClick={() => {
                    setLocale(item.identifier);

                    if (closeOnChange) {
                      closePopover();
                    }
                  }}
                  startIcon={flag && <Div sx={{ fontSize: 28 }}>{flag}</Div>}
                  sx={{ ...(index > 0 && { marginTop: '2px' }) }}
                >
                  {item.title}
                </LocaleButton>
              );
            })}
          </div>
        )}
      </Popover>
    </Div>
  );
}

function LocaleButton({ children, ...props }: ButtonProps) {
  const palette = usePalette();

  const Button = styled(MUIButton)({
    textTransform: 'capitalize',
    alignItems: 'center',
    justifyContent: 'start',
    textAlign: 'left',
    fontSize: '0.9rem',
    padding: '0.5rem 1.2rem',
    ...((palette.salvon?.locale_button ?? {}) as TemplateStringsArray),
  });

  return (
    <Button fullWidth disableRipple {...props}>
      {children}
    </Button>
  );
}
