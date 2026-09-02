import '@mui/x-date-pickers/themeAugmentation';

import {
  CheckboxCheckedIcon,
  CheckboxIcon,
  CheckboxIndeterminateIcon,
  RadioCheckedIcon,
  RadioIcon,
} from './CheckboxIcons';
import { createElement } from 'react';

import { themeMode } from '@salvon/consts/theme-mode';
import type { Theme } from '@salvon/types';

const lightColors = {
  // accent blue
  accent: '#254a94',
  accentHover: '#2563eb',
  accentSoft: '#f5f8ff',
  secondary: '#516385',
  // neutral surfaces (light → dark)
  bg: '#eef1f6',
  surface: '#ffffff',
  surface2: '#ffffff',
  surface3: '#f8fafc',
  border: '#dbe1ea',
  borderStrong: '#c3ccda',
  borderSubtle: '#e7ebf1',
  // text
  text: '#334155',
  textSecondary: '#475569',
  textMuted: '#94a3b8',
};

const darkColors = {
  // accent blue
  accent: '#254a94',
  accentHover: '#2563eb',
  accentSoft: '#182034',
  secondary: '#2a53c9',
  // neutral surfaces (dark → light)
  bg: '#0a0a0a',
  surface: '#141414',
  surface2: '#181818',
  surface3: '#1e1e1e',
  border: '#303030',
  borderStrong: '#3d3d3d',
  borderSubtle: '#242424',
  // text
  text: '#eeeeee',
  textSecondary: '#cbd5e1',
  textMuted: '#8a8a8a',
};

export const breakpoints = {
  values: {
    xs: 0,
    sm: 600,
    md: 900,
    lg: 1200,
    xl: 1536,
  },
};

export const lightTheme: Theme = {
  breakpoints,
  palette: {
    mode: themeMode.light,
    primary: {
      main: lightColors.accent,
    },
    secondary: {
      main: lightColors.secondary,
    },
    success: {
      main: 'rgb(62,147,73)',
      contrastText: '#ffffff',
    },
    warning: {
      main: 'rgb(214,127,15)',
      contrastText: '#ffffff',
    },
    error: {
      main: 'rgb(182,25,15)',
      contrastText: '#ffffff',
    },
    info: {
      main: 'rgb(2,143,202)',
      contrastText: '#ffffff',
    },
    salvon: {
      background: {
        default: '#eef1f6',
        guest: '#eef1f6',
      },
      form: {
        labelMode: 'above',
      },
      accent_icon: {
        background: '#254a94',
        color: '#fff',
      },
      locale_button: {
        textDecoration: 'none',
        color: '#334155',
        '&.active, &:hover': {
          background: '#eef2f7',
        },
      },
      sidebar: {
        background: '#ffffff',
        borderRight: '1px solid #e7ebf1',
      },
      card: {
        borderRadius: '8px',
        border: '1px solid #dbe1ea',
        boxShadow: '0 1px 2px rgba(15, 23, 42, 0.04)',
      },
      topbar: {
        borderBottom: '1px solid #e7ebf1',
      },
      menu: {
        header: {
          color: '#94a3b8',
          fontWeight: 600,
          letterSpacing: '0.06em',
        },
        list_button: {
          background: 'transparent',
          color: '#475569',
          fontWeight: 500,
          padding: '4px 5px !important',
          '&:hover': {
            background: '#f8fafc !important',
          },
          '&.active': {
            background: '#eef2ff !important',
            color: lightColors.accent,
            fontWeight: 600,
          },
        },
        list_icon: {
          color: '#64748b',
        },
        list_icon_badge: {
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          flexShrink: 0,
          width: 28,
          height: 28,
          borderRadius: '7px',
          background: '#f1f5f9',
          border: '1px solid #e6ebf1',
          color: '#254a94',
          fontSize: '0.85rem',
          '.active-route &': {
            background: '#254a94',
            border: `1px solid #254a94`,
            color: '#ffffff',
          },
        },
      },
      popover: {
        paper: {
          background: '#ffffff',
        },
      },
      omni_search: {
        frame: '#e2e8f0',
        border: '#eef0f3',
        header: '#94a3b8',
        chipBg: '#f4f6f9',
        chipBorder: '#e6e9ef',
        activeBg: '#eef4ff',
        activeBorder: '#c7dbff',
        footerBg: '#fafbfc',
      },
      table: {
        header: {
          background: '#fff',
        },
        row: {},
        row_even: {
          background: '#fff',
        },
        linear_loader: {
          background: '#eaeaea',
          '.MuiLinearProgress-bar': {
            backgroundColor: '#cecece !important',
          },
        },
      },
      file_upload: {
        dropzone: {
          bg: '#f6f8fc',
          bgActive: lightColors.accentSoft,
          borderColor: {
            default: '#c7d0e0',
            accept: lightColors.accent,
            reject: 'rgb(182,25,15)',
          },
          badgeBg: lightColors.accent,
          badgeColor: '#ffffff',
          hintColor: lightColors.textMuted,
          linkColor: lightColors.text,
        },
        fileCard: {
          bg: '#f3f5f9',
          nameColor: lightColors.text,
          metaColor: lightColors.textMuted,
          tileBg: '#217346',
          tileColor: '#ffffff',
          removeColor: lightColors.textMuted,
        },
        progress: {
          track: '#dbe2ec',
          bar: lightColors.accentHover,
          label: lightColors.text,
        },
      },
      control_card: {
        borderColor: '#dbe1ea',
        backgroundColor: '#ffffff',
        '&:hover': {
          borderColor: '#c3ccda',
        },
        '&.checked': {
          borderColor: lightColors.accent,
          backgroundColor: '#f5f8ff',
        },
        '&.checked:hover': {
          borderColor: lightColors.accent,
        },
      },
      price_settlement: {
        header: {
          backgroundColor: lightColors.accent,
          color: '#ffffff',
        },
        limitBar: {
          track: '#e3e8f0',
          bar: 'rgb(62,147,73)',
          barExceeded: 'rgb(182,25,15)',
        },
        limitOk: 'rgb(62,147,73)',
        limitExceeded: 'rgb(182,25,15)',
        breakdownTotal: {
          color: lightColors.accent,
        },
        divider: '#dbe1ea',
      },
    },
  },
  components: {
    MuiPaper: {
      defaultProps: {
        elevation: 0,
      },
      styleOverrides: {
        root: {
          backgroundImage: 'none',
          backgroundColor: '#ffffff',
        },
      },
    },
    MuiDialog: {
      styleOverrides: {
        paper: {
          backgroundColor: '#fff',
        },
      },
    },
    MuiDrawer: {
      styleOverrides: {
        paper: {
          backgroundColor: '#fff',
        },
      },
    },
    MuiAutocomplete: {
      styleOverrides: {
        paper: {
          backgroundColor: '#ffffff',
          border: `1px solid ${lightColors.border}`,
          borderRadius: 8,
          boxShadow: '0 8px 24px rgba(15, 23, 42, 0.12)',
        },
        listbox: {
          padding: 4,
        },
        option: {
          fontSize: '0.875rem',
          color: lightColors.text,
          borderRadius: 6,
          padding: '6px 10px',
          minHeight: 'auto',
          '&:hover': {
            backgroundColor: lightColors.surface3,
          },
          '&.Mui-focused': {
            backgroundColor: lightColors.surface3,
          },
          '&[aria-selected="true"]': {
            backgroundColor: lightColors.accentSoft,
            color: lightColors.accent,
            fontWeight: 600,
          },
          '&[aria-selected="true"].Mui-focused': {
            backgroundColor: lightColors.accentSoft,
          },
        },
        noOptions: {
          fontSize: '0.875rem',
          color: lightColors.textMuted,
        },
        loading: {
          fontSize: '0.875rem',
          color: lightColors.textMuted,
        },
      },
    },
    MuiTextField: {
      defaultProps: {
        size: 'small',
      },
    },
    MuiFormLabel: {
      styleOverrides: {
        root: {
          fontSize: '0.875rem',
          fontWeight: 600,
          color: '#334155',
          marginBottom: 6,
          lineHeight: 1.4,
          '&.Mui-focused': {
            color: '#334155',
          },
          '& .MuiFormLabel-asterisk': {
            color: '#e11d48',
          },
        },
      },
    },
    MuiFormControlLabel: {
      styleOverrides: {
        label: {
          fontSize: '0.875rem',
          fontWeight: 600,
          color: '#334155',
        },
      },
    },
    MuiCheckbox: {
      defaultProps: {
        disableRipple: true,
        icon: createElement(CheckboxIcon, { borderColor: '#c3ccda' }),
        checkedIcon: createElement(CheckboxCheckedIcon, {
          fill: lightColors.accent,
        }),
        indeterminateIcon: createElement(CheckboxIndeterminateIcon, {
          fill: lightColors.accent,
        }),
      },
      styleOverrides: {
        root: {
          padding: 6,
          borderRadius: 6,
          '&:hover': {
            backgroundColor: 'transparent',
          },
        },
      },
    },
    MuiRadio: {
      defaultProps: {
        disableRipple: true,
        icon: createElement(RadioIcon, { borderColor: '#c3ccda' }),
        checkedIcon: createElement(RadioCheckedIcon, {
          fill: lightColors.accent,
        }),
      },
      styleOverrides: {
        root: {
          padding: 6,
          '&:hover': {
            backgroundColor: 'transparent',
          },
        },
      },
    },
    MuiPickersOutlinedInput: {
      styleOverrides: {
        root: {
          borderRadius: 8,
          backgroundColor: '#ffffff',
          transition: 'border-color 0.15s ease, box-shadow 0.15s ease',
          '& .MuiPickersOutlinedInput-notchedOutline': {
            borderColor: '#dbe1ea',
          },
          '&:hover .MuiPickersOutlinedInput-notchedOutline': {
            borderColor: '#c3ccda',
          },
          '&.Mui-focused .MuiPickersOutlinedInput-notchedOutline': {
            borderColor: lightColors.accent,
            borderWidth: 1,
          },
          '&.Mui-focused': {
            boxShadow: `0 0 0 3px ${lightColors.accent}1f`,
          },
        },
      },
    },
    MuiPickersInputBase: {
      styleOverrides: {
        sectionsContainer: {
          padding: '8px 0',
          fontSize: '0.875rem',
        },
      },
    },
    MuiOutlinedInput: {
      styleOverrides: {
        root: {
          borderRadius: 8,
          backgroundColor: '#ffffff',
          transition: 'border-color 0.15s ease, box-shadow 0.15s ease',
          '& .MuiOutlinedInput-notchedOutline': {
            borderColor: '#dbe1ea',
          },
          '&:hover .MuiOutlinedInput-notchedOutline': {
            borderColor: '#c3ccda',
          },
          '&.Mui-focused .MuiOutlinedInput-notchedOutline': {
            borderColor: lightColors.accent,
            borderWidth: 1,
          },
          '&.Mui-focused': {
            boxShadow: `0 0 0 3px ${lightColors.accent}1f`,
          },
          '&.Mui-error.Mui-focused': {
            boxShadow: '0 0 0 3px rgba(225,29,72,0.12)',
          },
          '&.Mui-disabled': {
            backgroundColor: '#f8fafc',
          },
        },
        input: {
          padding: '8px 12px',
          fontSize: '0.875rem',
        },
      },
    },
    MuiInputBase: {
      styleOverrides: {
        sizeSmall: {
          '& .MuiOutlinedInput-input, & input': {
            padding: '8px 12px',
          },
        },
      },
    },
    MuiFormHelperText: {
      styleOverrides: {
        root: {
          marginLeft: 2,
          marginTop: 5,
          fontSize: '0.75rem',
        },
      },
    },
    MuiButton: {
      defaultProps: {
        size: 'medium',
        disableRipple: true,
      },
      styleOverrides: {
        root: {
          textTransform: 'none',
          fontWeight: 600,
          borderRadius: 8,
          boxShadow: 'none',
          '&:hover': {
            boxShadow: 'none',
          },
        },
        sizeSmall: {
          fontSize: '0.75rem',
          padding: '4px 10px',
        },
        sizeMedium: {
          fontSize: '0.8125rem',
          padding: '5px 12px',
        },
        sizeLarge: {
          fontSize: '0.875rem',
          padding: '7px 16px',
        },
        startIcon: {
          '& > *:nth-of-type(1)': { fontSize: '1.2em' },
        },
        endIcon: {
          '& > *:nth-of-type(1)': { fontSize: '1.2em' },
        },
      },
      variants: [
        {
          props: {
            variant: 'outlined',
            color: 'primary',
          },
          style: {
            borderColor: '#202121',
            color: '#202121',
          },
        },
      ],
    },
    MuiTabs: {
      styleOverrides: {
        root: {
          minHeight: 0,
          padding: 4,
          borderRadius: 8,
          backgroundColor: lightColors.bg,
          border: `1px solid ${lightColors.border}`,
        },
        indicator: {
          display: 'none',
        },
        list: {
          gap: 4,
        },
        scrollButtons: {
          borderRadius: 6,
          transition: 'background-color .15s, color .15s',
          '&:hover': {
            backgroundColor: 'rgba(127,127,127,0.12)',
          },
          '& .MuiTouchRipple-root': {
            display: 'none',
          },
        },
      },
    },
    MuiTab: {
      defaultProps: {
        disableRipple: true,
      },
      styleOverrides: {
        root: {
          minHeight: 0,
          minWidth: 0,
          padding: '6px 16px',
          borderRadius: 6,
          textTransform: 'none',
          fontWeight: 500,
          color: lightColors.textSecondary,
          transition: 'background-color .15s, color .15s, box-shadow .15s',
          border: '1px solid transparent',
          '&:hover': {
            color: lightColors.text,
            backgroundColor: 'rgba(0,0,0,0.03)',
          },
          '&.Mui-selected': {
            // color: lightColors.text,
            // backgroundColor: lightColors.surface,
            // borderColor: '#dbe1ea',
            fontWeight: 600,
            color: '#fff',
            background: lightColors.accent,
          },
        },
      },
    },
  },
  shape: {
    borderRadius: 5,
  },
};

export const darkTheme: Theme = {
  breakpoints,
  palette: {
    mode: themeMode.dark,
    primary: {
      main: darkColors.accent,
    },
    secondary: {
      main: darkColors.secondary,
    },
    success: {
      main: 'rgb(62,147,73)',
      contrastText: '#ffffff',
    },
    warning: {
      main: 'rgb(214,127,15)',
      contrastText: '#ffffff',
    },
    error: {
      main: 'rgb(182,25,15)',
      contrastText: '#ffffff',
    },
    info: {
      main: 'rgb(2,143,202)',
      contrastText: '#ffffff',
    },
    salvon: {
      background: {
        default: '#0a0a0a',
        guest: '#0a0a0a',
      },
      form: {
        labelMode: 'above',
      },
      accent_icon: {
        background: darkColors.accent,
        color: '#fff',
      },
      locale_button: {
        textDecoration: 'none',
        color: '#d0d0d0',
        '&.active, &:hover': {
          background: '#222222',
        },
      },
      sidebar: {
        background: '#141414',
        borderRight: '1px solid #242424',
      },
      card: {
        background: '#181818',
        borderRadius: '8px',
        border: '1px solid #303030',
        boxShadow: '0 1px 2px rgba(0, 0, 0, 0.4)',
      },
      topbar: {
        background: '#141414',
        borderBottom: '1px solid #242424',
      },
      page_container: {
        background: '#0a0a0a',
      },
      menu: {
        header: {
          color: '#8a8a8a',
          fontWeight: 600,
          letterSpacing: '0.06em',
        },
        list_button: {
          background: 'transparent',
          color: '#d6d6d6',
          fontWeight: 500,
          padding: '4px 5px !important',
          '&:hover': {
            background: '#1e1e1e !important',
          },
          '&.active': {
            background: '#182034 !important',
            color: '#7aa2ff',
            fontWeight: 600,
          },
        },
        list_icon: {
          color: '#c4c4c4',
        },
        list_icon_badge: {
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          flexShrink: 0,
          width: 28,
          height: 28,
          borderRadius: '7px',
          background: '#1e1e1e',
          border: '1px solid #2c2c2c',
          color: '#c4c4c4',
          fontSize: '0.85rem',
          '.active-route &': {
            background: '#2a53c9',
            border: '1px solid #2a53c9',
            color: '#ffffff',
          },
        },
      },
      popover: {
        paper: {
          background: '#141414',
        },
      },
      omni_search: {
        frame: '#cecece',
        border: '#2c2c2f',
        header: '#6b7280',
        chipBg: '#1c1c1f',
        chipBorder: '#2c2c31',
        activeBg: 'rgba(59,130,246,0.16)',
        activeBorder: 'rgba(59,130,246,0.45)',
        footerBg: '#0c0c0e',
      },
      table: {
        header: {
          background: '#141414',
        },
        row: {},
        row_even: {
          background: '#191919',
        },
        linear_loader: {
          background: '#3a3a3a',
          '.MuiLinearProgress-bar': {
            backgroundColor: '#7e7e7e !important',
          },
        },
      },
      file_upload: {
        dropzone: {
          bg: '#161616',
          bgActive: darkColors.accentSoft,
          borderColor: {
            default: '#3d3d3d',
            accept: darkColors.secondary,
            reject: 'rgb(182,25,15)',
          },
          badgeBg: darkColors.accentHover,
          badgeColor: '#ffffff',
          hintColor: darkColors.textMuted,
          linkColor: darkColors.text,
        },
        fileCard: {
          bg: '#1e1e1e',
          nameColor: darkColors.text,
          metaColor: darkColors.textMuted,
          tileBg: '#217346',
          tileColor: '#ffffff',
          removeColor: darkColors.textMuted,
        },
        progress: {
          track: '#2a2a2a',
          bar: darkColors.secondary,
          label: darkColors.text,
        },
      },
      control_card: {
        borderColor: '#303030',
        backgroundColor: '#181818',
        '&:hover': {
          borderColor: '#3d3d3d',
        },
        '&.checked': {
          borderColor: '#5b8bff',
          backgroundColor: '#182034',
        },
        '&.checked:hover': {
          borderColor: '#5b8bff',
        },
      },
      price_settlement: {
        header: {
          backgroundColor: '#182034',
          color: '#ffffff',
        },
        limitBar: {
          track: '#2a2a2a',
          bar: 'rgb(62,147,73)',
          barExceeded: 'rgb(182,25,15)',
        },
        limitOk: 'rgb(88,190,101)',
        limitExceeded: 'rgb(214,72,63)',
        breakdownTotal: {
          color: '#7aa2ff',
        },
        divider: '#303030',
      },
    },
  },
  components: {
    MuiPaper: {
      defaultProps: {
        elevation: 0,
      },
      styleOverrides: {
        root: {
          backgroundImage: 'none',
          backgroundColor: '#242424',
        },
      },
    },
    MuiDialog: {
      styleOverrides: {
        paper: {
          backgroundColor: '#171717',
        },
      },
    },
    MuiDrawer: {
      styleOverrides: {
        paper: {
          backgroundColor: '#171717',
        },
      },
    },
    MuiAutocomplete: {
      styleOverrides: {
        paper: {
          backgroundColor: darkColors.surface2,
          border: `1px solid ${darkColors.border}`,
          borderRadius: 8,
          boxShadow: '0 8px 24px rgba(0, 0, 0, 0.5)',
        },
        listbox: {
          padding: 4,
        },
        option: {
          fontSize: '0.875rem',
          color: darkColors.text,
          borderRadius: 6,
          padding: '6px 10px',
          minHeight: 'auto',
          '&:hover': {
            backgroundColor: darkColors.surface3,
          },
          '&.Mui-focused': {
            backgroundColor: darkColors.surface3,
          },
          '&[aria-selected="true"]': {
            backgroundColor: darkColors.accentSoft,
            color: '#7aa2ff',
            fontWeight: 600,
          },
          '&[aria-selected="true"].Mui-focused': {
            backgroundColor: darkColors.accentSoft,
          },
        },
        noOptions: {
          fontSize: '0.875rem',
          color: darkColors.textMuted,
        },
        loading: {
          fontSize: '0.875rem',
          color: darkColors.textMuted,
        },
      },
    },
    MuiTextField: {
      defaultProps: {
        size: 'small',
      },
    },
    MuiFormLabel: {
      styleOverrides: {
        root: {
          fontSize: '0.875rem',
          fontWeight: 600,
          color: darkColors.textSecondary,
          marginBottom: 6,
          lineHeight: 1.4,
          '&.Mui-focused': {
            color: darkColors.textSecondary,
          },
          '& .MuiFormLabel-asterisk': {
            color: '#fb7185',
          },
        },
      },
    },
    MuiFormControlLabel: {
      styleOverrides: {
        label: {
          fontSize: '0.875rem',
          fontWeight: 600,
          color: darkColors.textSecondary,
        },
      },
    },
    MuiCheckbox: {
      defaultProps: {
        disableRipple: true,
        icon: createElement(CheckboxIcon, {
          borderColor: darkColors.borderStrong,
        }),
        checkedIcon: createElement(CheckboxCheckedIcon, {
          fill: darkColors.accent,
        }),
        indeterminateIcon: createElement(CheckboxIndeterminateIcon, {
          fill: darkColors.accent,
        }),
      },
      styleOverrides: {
        root: {
          padding: 6,
          borderRadius: 6,
          '&:hover': {
            backgroundColor: 'transparent',
          },
        },
      },
    },
    MuiRadio: {
      defaultProps: {
        disableRipple: true,
        icon: createElement(RadioIcon, {
          borderColor: darkColors.borderStrong,
        }),
        checkedIcon: createElement(RadioCheckedIcon, {
          fill: darkColors.accent,
        }),
      },
      styleOverrides: {
        root: {
          padding: 6,
          '&:hover': {
            backgroundColor: 'transparent',
          },
        },
      },
    },
    MuiPickersOutlinedInput: {
      styleOverrides: {
        root: {
          borderRadius: 8,
          backgroundColor: darkColors.surface3,
          transition: 'border-color 0.15s ease, box-shadow 0.15s ease',
          '& .MuiPickersOutlinedInput-notchedOutline': {
            borderColor: darkColors.border,
          },
          '&:hover .MuiPickersOutlinedInput-notchedOutline': {
            borderColor: darkColors.borderStrong,
          },
          '&.Mui-focused .MuiPickersOutlinedInput-notchedOutline': {
            borderColor: darkColors.accent,
            borderWidth: 1,
          },
          '&.Mui-focused': {
            boxShadow: '0 0 0 3px rgba(91,139,255,0.2)',
          },
        },
      },
    },
    MuiPickersInputBase: {
      styleOverrides: {
        sectionsContainer: {
          padding: '8px 0',
          fontSize: '0.875rem',
        },
      },
    },
    MuiOutlinedInput: {
      styleOverrides: {
        root: {
          borderRadius: 8,
          backgroundColor: darkColors.surface3,
          transition: 'border-color 0.15s ease, box-shadow 0.15s ease',
          '& .MuiOutlinedInput-notchedOutline': {
            borderColor: darkColors.border,
          },
          '&:hover .MuiOutlinedInput-notchedOutline': {
            borderColor: darkColors.borderStrong,
          },
          '&.Mui-focused .MuiOutlinedInput-notchedOutline': {
            borderColor: darkColors.accent,
            borderWidth: 1,
          },
          '&.Mui-focused': {
            boxShadow: '0 0 0 3px rgba(91,139,255,0.2)',
          },
          '&.Mui-error.Mui-focused': {
            boxShadow: '0 0 0 3px rgba(251,113,133,0.18)',
          },
          '&.Mui-disabled': {
            backgroundColor: darkColors.surface,
          },
        },
        input: {
          padding: '8px 12px',
          fontSize: '0.875rem',
        },
      },
    },
    MuiInputBase: {
      styleOverrides: {
        sizeSmall: {
          '& .MuiOutlinedInput-input, & input': {
            padding: '8px 12px',
          },
        },
      },
    },
    MuiFormHelperText: {
      styleOverrides: {
        root: {
          marginLeft: 2,
          marginTop: 5,
          fontSize: '0.75rem',
        },
      },
    },
    MuiButton: {
      defaultProps: {
        size: 'medium',
        disableRipple: true,
      },
      styleOverrides: {
        root: {
          textTransform: 'none',
          fontWeight: 600,
          borderRadius: 8,
          boxShadow: 'none',
          '&:hover': {
            boxShadow: 'none',
          },
        },
        sizeSmall: {
          fontSize: '0.75rem',
          padding: '4px 10px',
        },
        sizeMedium: {
          fontSize: '0.8125rem',
          padding: '5px 12px',
        },
        sizeLarge: {
          fontSize: '0.875rem',
          padding: '7px 16px',
        },
        startIcon: {
          '& > *:nth-of-type(1)': { fontSize: '1.2em' },
        },
        endIcon: {
          '& > *:nth-of-type(1)': { fontSize: '1.2em' },
        },
      },
      variants: [
        {
          props: {
            variant: 'outlined',
            color: 'primary',
          },
          style: {
            borderColor: '#afafaf',
            color: '#afafaf',
            '&:hover': {
              background: '#2b2b2b',
            },
          },
        },
      ],
    },
    MuiTabs: {
      styleOverrides: {
        root: {
          minHeight: 0,
          padding: 4,
          borderRadius: 8,
          backgroundColor: 'rgba(255,255,255,0.06)',
          border: `1px solid ${darkColors.border}`,
        },
        indicator: {
          display: 'none',
        },
        list: {
          gap: 4,
        },
        scrollButtons: {
          borderRadius: 6,
          transition: 'background-color .15s, color .15s',
          '&:hover': {
            backgroundColor: 'rgba(127,127,127,0.12)',
          },
          '& .MuiTouchRipple-root': {
            display: 'none',
          },
        },
      },
    },
    MuiTab: {
      defaultProps: {
        disableRipple: true,
      },
      styleOverrides: {
        root: {
          minHeight: 0,
          minWidth: 0,
          padding: '6px 16px',
          borderRadius: 6,
          textTransform: 'none',
          fontWeight: 500,
          color: darkColors.textSecondary,
          transition: 'background-color .15s, color .15s, box-shadow .15s',
          border: '1px solid transparent',
          '&:hover': {
            color: darkColors.text,
            backgroundColor: 'rgba(255,255,255,0.04)',
          },
          '&.Mui-selected': {
            // color: darkColors.text,
            // backgroundColor: darkColors.surface,
            // borderColor: '#3f3f3f',
            fontWeight: 600,
            color: '#fff',
            background: lightColors.accent,
          },
        },
      },
    },
  },
  shape: {
    borderRadius: 5,
  },
};
