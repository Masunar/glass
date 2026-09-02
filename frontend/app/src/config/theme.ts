import '@mui/x-date-pickers/themeAugmentation';

import {
  CheckboxCheckedIcon,
  CheckboxIcon,
  CheckboxIndeterminateIcon,
  RadioCheckedIcon,
  RadioIcon,
} from './CheckboxIcons';
import { industry } from './tokens';
import { createElement } from 'react';

import { themeMode } from '@salvon/consts/theme-mode';
import type { Theme } from '@salvon/types';

const lightColors = {
  accent: industry.accent.base,
  accentHover: industry.accent[700],
  accentSoft: industry.accent[100],
  secondary: industry.accent[600],
  bg: industry.bg,
  surface: industry.surface,
  surface2: industry.surface,
  surface3: industry.neutral[100],
  border: industry.neutral[300],
  borderStrong: industry.neutral[400],
  borderSubtle: industry.neutral[200],
  text: industry.text,
  textSecondary: industry.neutral[700],
  textMuted: industry.neutral[500],
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
        default: industry.bg,
        guest: industry.bg,
      },
      form: {
        labelMode: 'above',
      },
      accent_icon: {
        background: industry.accent.base,
        color: '#fff',
      },
      locale_button: {
        textDecoration: 'none',
        color: industry.text,
        '&.active, &:hover': {
          background: industry.neutral[100],
        },
      },
      sidebar: {
        background: '#ffffff',
        borderRight: `1px solid ${industry.neutral[200]}`,
      },
      card: {
        borderRadius: industry.radius,
        border: `1px solid ${industry.neutral[300]}`,
        boxShadow: '0 1px 2px rgba(15, 23, 42, 0.04)',
      },
      topbar: {
        borderBottom: `1px solid ${industry.neutral[200]}`,
      },
      menu: {
        header: {
          color: industry.neutral[500],
          fontWeight: 600,
          letterSpacing: '0.06em',
        },
        list_button: {
          background: 'transparent',
          color: industry.neutral[700],
          fontWeight: 500,
          padding: '4px 5px !important',
          '&:hover': {
            background: `${industry.neutral[100]} !important`,
          },
          '&.active': {
            background: `${industry.accent[100]} !important`,
            color: lightColors.accent,
            fontWeight: 600,
          },
        },
        list_icon: {
          color: industry.neutral[600],
        },
        list_icon_badge: {
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          flexShrink: 0,
          width: 28,
          height: 28,
          borderRadius: industry.radius,
          background: industry.neutral[100],
          border: `1px solid ${industry.neutral[200]}`,
          color: industry.accent.base,
          fontSize: '0.85rem',
          '.active-route &': {
            background: industry.accent.base,
            border: `1px solid ${industry.accent.base}`,
            color: industry.surface,
          },
        },
      },
      popover: {
        paper: {
          background: '#ffffff',
        },
      },
      omni_search: {
        frame: industry.neutral[200],
        border: industry.neutral[200],
        header: industry.neutral[500],
        chipBg: industry.neutral[100],
        chipBorder: industry.neutral[200],
        activeBg: industry.accent[100],
        activeBorder: industry.accent[300],
        footerBg: industry.neutral[100],
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
          background: industry.neutral[200],
          '.MuiLinearProgress-bar': {
            backgroundColor: `${industry.neutral[400]} !important`,
          },
        },
      },
      file_upload: {
        dropzone: {
          bg: industry.neutral[100],
          bgActive: lightColors.accentSoft,
          borderColor: {
            default: industry.neutral[400],
            accept: lightColors.accent,
            reject: 'rgb(182,25,15)',
          },
          badgeBg: lightColors.accent,
          badgeColor: '#ffffff',
          hintColor: lightColors.textMuted,
          linkColor: lightColors.text,
        },
        fileCard: {
          bg: industry.bg,
          nameColor: lightColors.text,
          metaColor: lightColors.textMuted,
          tileBg: '#217346',
          tileColor: '#ffffff',
          removeColor: lightColors.textMuted,
        },
        progress: {
          track: industry.neutral[200],
          bar: lightColors.accentHover,
          label: lightColors.text,
        },
      },
      control_card: {
        borderColor: industry.neutral[300],
        backgroundColor: '#ffffff',
        '&:hover': {
          borderColor: industry.neutral[400],
        },
        '&.checked': {
          borderColor: lightColors.accent,
          backgroundColor: industry.accent[100],
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
          track: industry.neutral[200],
          bar: 'rgb(62,147,73)',
          barExceeded: 'rgb(182,25,15)',
        },
        limitOk: 'rgb(62,147,73)',
        limitExceeded: 'rgb(182,25,15)',
        breakdownTotal: {
          color: lightColors.accent,
        },
        divider: industry.neutral[300],
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
          borderRadius: industry.radius,
          boxShadow: '0 8px 24px rgba(15, 23, 42, 0.12)',
        },
        listbox: {
          padding: 4,
        },
        option: {
          fontSize: '0.875rem',
          color: lightColors.text,
          borderRadius: industry.radius,
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
          color: industry.text,
          marginBottom: 6,
          lineHeight: 1.4,
          '&.Mui-focused': {
            color: industry.text,
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
          color: industry.text,
        },
      },
    },
    MuiCheckbox: {
      defaultProps: {
        disableRipple: true,
        icon: createElement(CheckboxIcon, {
          borderColor: industry.neutral[400],
        }),
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
          borderRadius: industry.radius,
          '&:hover': {
            backgroundColor: 'transparent',
          },
        },
      },
    },
    MuiRadio: {
      defaultProps: {
        disableRipple: true,
        icon: createElement(RadioIcon, { borderColor: industry.neutral[400] }),
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
          borderRadius: industry.radius,
          backgroundColor: '#ffffff',
          transition: 'border-color 0.15s ease, box-shadow 0.15s ease',
          '& .MuiPickersOutlinedInput-notchedOutline': {
            borderColor: industry.neutral[300],
          },
          '&:hover .MuiPickersOutlinedInput-notchedOutline': {
            borderColor: industry.neutral[400],
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
          borderRadius: industry.radius,
          backgroundColor: '#ffffff',
          transition: 'border-color 0.15s ease, box-shadow 0.15s ease',
          '& .MuiOutlinedInput-notchedOutline': {
            borderColor: industry.neutral[300],
          },
          '&:hover .MuiOutlinedInput-notchedOutline': {
            borderColor: industry.neutral[400],
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
            backgroundColor: industry.neutral[100],
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
          borderRadius: industry.radius,
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
          borderRadius: industry.radius,
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
          borderRadius: industry.radius,
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
          borderRadius: industry.radius,
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
            // borderColor: industry.neutral[300],
            fontWeight: 600,
            color: '#fff',
            background: lightColors.accent,
          },
        },
      },
    },
  },
  typography: {
    fontFamily: industry.font.body,
    // Barlow Condensed niesie liczby, numery zlecen i tytuly - to on daje
    // gestosc. Naglowki tresci ida tym samym krojem, zeby ekran mial
    // jeden rytm, a nie dwa.
    h1: {
      fontFamily: industry.font.heading,
      fontWeight: 600,
      letterSpacing: '-0.015em',
    },
    h2: {
      fontFamily: industry.font.heading,
      fontWeight: 600,
      letterSpacing: '-0.015em',
    },
    h3: {
      fontFamily: industry.font.heading,
      fontWeight: 600,
      letterSpacing: '-0.015em',
    },
    h4: {
      fontFamily: industry.font.heading,
      fontWeight: 600,
      letterSpacing: '-0.015em',
    },
    h5: { fontFamily: industry.font.heading, fontWeight: 600 },
    h6: { fontFamily: industry.font.heading, fontWeight: 600 },
    button: { textTransform: 'none', fontWeight: 500 },
  },
  shape: {
    borderRadius: industry.radius,
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
