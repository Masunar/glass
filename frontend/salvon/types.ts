import type {
  Theme as MUITheme,
  PaletteOptions,
  SxProps,
  ThemeOptions,
} from '@mui/material';

import type {
  CSSProperties,
  Dispatch,
  FunctionComponent,
  JSX,
  ReactNode,
  SetStateAction,
} from 'react';

export type SlotItem<T> = Partial<T>;

export type SetState<T> = Dispatch<SetStateAction<T>>;

export type NoArgsVoidFunction = () => void;

export type Noop = NoArgsVoidFunction;

export type ControllableOptionalState = {
  open?: boolean;
  setOpen?: SetState<boolean>;
};

export type CloneableElement = JSX.Element;

export type AnchorElement = {
  anchor?: CloneableElement;
};

export type CallableNode<T> = ReactNode | ((props: T) => ReactNode);

export type CallableChildren<T> = {
  children: CallableNode<T>;
};

export type ContextSetStateAction<T> =
  Dispatch<SetStateAction<T>> | (() => undefined);

export type Locales = Array<{
  identifier: string;
  name: string;
  title: string;
  flag?: string | FunctionComponent;
}>;

export type Theme = Omit<ThemeOptions, 'palette'> & {
  palette: Omit<PaletteOptions, 'mode'> & {
    mode: string;
    salvon?: {
      background?: {
        default?: string;
        guest: string;
      };
      form?: {
        labelMode: 'material' | 'above';
      };
      accent_icon?: SxProps<MUITheme>;
      locale_button?: SxProps<MUITheme>;
      sidebar?: CSSProperties;
      card?: SxProps<MUITheme>;
      topbar?: SxProps<MUITheme>;
      page_container?: SxProps<MUITheme>;
      menu?: {
        header?: SxProps<MUITheme>;
        divider?: SxProps<MUITheme>;
        list_button?: SxProps<MUITheme>;
        list_icon?: SxProps<MUITheme>;
        list_icon_badge?: SxProps<MUITheme>;
      };
      popover?: {
        paper?: SxProps<MUITheme>;
      };
      omni_search?: {
        frame?: string;
        border?: string;
        header?: string;
        chipBg?: string;
        chipBorder?: string;
        activeBg?: string;
        activeBorder?: string;
        footerBg?: string;
      };
      table?: {
        header?: SxProps<MUITheme>;
        row?: SxProps<MUITheme>;
        row_even?: SxProps<MUITheme>;
        linear_loader?: SxProps<MUITheme>;
      };
      file_upload?: {
        dropzone?: {
          bg?: string;
          bgActive?: string;
          borderColor?: {
            default?: string;
            accept?: string;
            reject?: string;
          };
          badgeBg?: string;
          badgeColor?: string;
          hintColor?: string;
          linkColor?: string;
        };
        fileCard?: {
          bg?: string;
          nameColor?: string;
          metaColor?: string;
          tileBg?: string;
          tileColor?: string;
          removeColor?: string;
        };
        progress?: {
          track?: string;
          bar?: string;
          label?: string;
        };
      };
      control_card?: SxProps<MUITheme>;
      kanban?: {
        column?: SxProps<MUITheme>;
        columnBody?: SxProps<MUITheme>;
        columnBodyActive?: SxProps<MUITheme>;
        columnBodyBlocked?: SxProps<MUITheme>;
        card?: SxProps<MUITheme>;
      };
      scheduler?: SxProps<MUITheme>;
      data_hub?: {
        card?: SxProps<MUITheme>;
        row?: SxProps<MUITheme>;
        icon_badge?: SxProps<MUITheme>;
        search_field?: SxProps<MUITheme>;
      };
      settings_center?: {
        root?: SxProps<MUITheme>;
        header?: SxProps<MUITheme>;
        sidebar?: SxProps<MUITheme>;
        sidebarItem?: SxProps<MUITheme>;
        sidebarItemActive?: SxProps<MUITheme>;
        content?: SxProps<MUITheme>;
        countBadge?: SxProps<MUITheme>;
        countBadgeActive?: SxProps<MUITheme>;
        groupLabel?: SxProps<MUITheme>;
        search_field?: SxProps<MUITheme>;
        toggleRow?: SxProps<MUITheme>;
        toggleIcon?: SxProps<MUITheme>;
        toggleIconActive?: SxProps<MUITheme>;
      };
      price_settlement?: {
        root?: SxProps<MUITheme>;
        header?: SxProps<MUITheme>;
        headerTitle?: SxProps<MUITheme>;
        headerValue?: SxProps<MUITheme>;
        headerSubtitle?: SxProps<MUITheme>;
        section?: SxProps<MUITheme>;
        limitBar?: {
          track?: string;
          bar?: string;
          barExceeded?: string;
        };
        limitOk?: string;
        limitExceeded?: string;
        breakdownTitle?: SxProps<MUITheme>;
        rowLabel?: SxProps<MUITheme>;
        rowValue?: SxProps<MUITheme>;
        rowLabelHighlighted?: SxProps<MUITheme>;
        breakdownTotal?: SxProps<MUITheme>;
        divider?: string;
      };
    };
  };
};
