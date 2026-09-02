import { type Theme } from '@salvon/types';

export const schedulerSx = (
  palette: Theme['palette'],
): Record<string, unknown> => {
  const token = (palette?.salvon?.scheduler ?? {}) as Record<string, unknown>;
  const primary =
    (palette?.primary as { main?: string } | undefined)?.main ?? '#1976d2';

  return {
    height: '100%',
    fontSize: 13,
    color: 'text.primary',

    '& .rbc-toolbar': { display: 'none' },

    '& .rbc-month-header, & .rbc-time-header-content': {
      backgroundColor: 'action.hover',
    },
    '& .rbc-header': {
      py: 1,
      fontSize: 11,
      fontWeight: 700,
      letterSpacing: 0.4,
      textTransform: 'uppercase',
      color: 'text.secondary',
      borderColor: 'divider',
    },
    '& .rbc-header + .rbc-header': {
      borderLeft: '1px solid',
      borderColor: 'divider',
    },
    '& .rbc-row-bg': { right: '0 !important' },

    '& .rbc-month-view, & .rbc-time-view, & .rbc-agenda-view': {
      border: '1px solid',
      borderColor: 'divider',
      borderRadius: 2,
      overflow: 'hidden',
    },
    '& .rbc-month-row + .rbc-month-row': { borderColor: 'divider' },
    '& .rbc-day-bg + .rbc-day-bg': { borderColor: 'divider' },
    '& .rbc-off-range-bg': { backgroundColor: 'action.hover' },
    '& .rbc-off-range .rbc-button-link': { color: 'text.disabled' },

    '& .rbc-month-view .rbc-day-bg.rbc-selected-cell': {
      backgroundColor: `color-mix(in srgb, ${primary} 30%, transparent)`,
      boxShadow: `inset 0 0 0 1px color-mix(in srgb, ${primary} 55%, transparent)`,
    },

    '& .rbc-date-cell': {
      p: 0.75,
      fontSize: 12,
      color: 'text.secondary',
    },
    '& .rbc-button-link': { color: 'inherit' },

    '& .rbc-month-view .rbc-today': { backgroundColor: 'transparent' },
    '& .rbc-day-slot.rbc-today': { backgroundColor: 'action.hover' },
    '& .rbc-time-header .rbc-today': { backgroundColor: 'transparent' },
    '& .rbc-now .rbc-button-link': {
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      minWidth: 22,
      height: 22,
      borderRadius: '50%',
      backgroundColor: 'primary.main',
      color: 'primary.contrastText',
      fontWeight: 700,
    },

    '& .rbc-event, & .rbc-day-slot .rbc-event, & .rbc-day-slot .rbc-background-event':
      {
        borderRadius: 1,
        padding: 0,
        border: 'none',
        boxShadow: 'none',
        '&:focus': { outline: 'none' },
      },
    '& .rbc-event.rbc-selected': { boxShadow: 'none', outline: 'none' },
    '& .rbc-event-content': { fontSize: 12 },

    '& .rbc-show-more': {
      fontSize: 11,
      color: 'text.secondary',
      backgroundColor: 'transparent',
      fontWeight: 500,
    },

    '& .rbc-time-content, & .rbc-time-header-content, & .rbc-timeslot-group, & .rbc-day-slot .rbc-time-slot':
      { borderColor: 'divider' },

    '& .rbc-time-view .rbc-timeslot-group': { minHeight: 46 },
    '& .rbc-time-gutter .rbc-timeslot-group': { borderBottom: 'none' },
    '& .rbc-time-gutter .rbc-label': {
      fontSize: 11,
      fontWeight: 500,
      color: 'text.disabled',
      pr: 1,
    },

    '& .rbc-day-slot .rbc-event': {
      borderRadius: '6px',
      padding: '2px 5px',
    },
    '& .rbc-day-slot .rbc-event-label': {
      fontSize: 10,
      color: 'inherit',
      opacity: 0.75,
    },

    '& .rbc-slot-selection': {
      backgroundColor: `color-mix(in srgb, ${primary} 30%, transparent)`,
      border: `1px solid color-mix(in srgb, ${primary} 55%, transparent)`,
      color: 'text.primary',
    },

    '& .rbc-current-time-indicator': {
      height: '2px',
      backgroundColor: 'primary.main',
      '&::before': {
        content: '""',
        position: 'absolute',
        left: 0,
        top: '50%',
        transform: 'translate(-50%, -50%)',
        width: 8,
        height: 8,
        borderRadius: '50%',
        backgroundColor: 'primary.main',
      },
    },

    '& .rbc-time-content': {
      scrollbarWidth: 'none',
      '&::-webkit-scrollbar': { display: 'none' },
    },
    '& .rbc-time-header.rbc-overflowing': {
      marginRight: '0 !important',
      borderRight: 'none !important',
    },
    '& .rbc-time-view .rbc-day-bg + .rbc-day-bg': { borderLeft: 'none' },

    '& .rbc-agenda-view table.rbc-agenda-table tbody > tr > td': {
      borderColor: 'divider',
    },

    ...token,
  };
};
