import { TextField, Typography } from '@mui/material';

import EditControls from './EditControls';
import type { TaskDrawerSaveResult } from './types.d';
import { BiPencil } from 'react-icons/bi';

import { useInlineEdit } from '@salvon/hooks/useInlineEdit';
import { useIsDarkMode } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { voc } from '@salvon/utils/object';

type Props = {
  title: string;
  editable?: boolean;
  onSave?: (
    value: string,
  ) => TaskDrawerSaveResult | Promise<TaskDrawerSaveResult>;
};

export default function TaskDrawerTitle({
  title,
  editable = true,
  onSave,
}: Props) {
  const t = useTranslation();
  const isDark = useIsDarkMode();

  const { editing, begin, cancel, save, value, setValue, loading } =
    useInlineEdit(title, onSave);

  if (editing) {
    return (
      <TextField
        value={value}
        disabled={loading}
        autoFocus
        variant="standard"
        sx={{
          flex: 1,
          mr: 3,
          '& .MuiInputBase-input': { fontSize: '1.24rem', fontWeight: 700 },
        }}
        onChange={(event) => setValue(event.target.value)}
        onBlur={() => (value.length === 0 ? cancel() : save())}
        onKeyDown={(event) => {
          if (event.key === 'Enter' && value.length > 0) {
            event.preventDefault();
            save();
          }
          if (event.key === 'Escape') cancel();
        }}
        slotProps={{
          input: {
            disableUnderline: true,
            endAdornment: (
              <EditControls save={save} cancel={cancel} loading={loading} />
            ),
          },
        }}
      />
    );
  }

  return (
    <Typography
      onClick={() => editable && begin()}
      sx={{
        flex: 1,
        mr: 3,
        fontSize: '1.24rem',
        fontWeight: 700,
        color: isDark ? 'text.primary' : '#1e293b',
        px: 1,
        py: 0.5,
        borderRadius: '9px',
        wordBreak: 'break-word',
        userSelect: 'none',
        display: 'inline-flex',
        alignItems: 'center',
        gap: 1,
        ...voc(editable, {
          cursor: 'text',
          '&:hover': { background: isDark ? '#222' : '#eee' },
        }),
      }}
    >
      {title}
      {editable && (
        <span style={{ display: 'flex' }} aria-label={t('edit')}>
          <BiPencil size={16} />
        </span>
      )}
    </Typography>
  );
}
