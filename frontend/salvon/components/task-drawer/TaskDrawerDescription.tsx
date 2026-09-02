import { TextField, Typography } from '@mui/material';

import TaskDrawerSection from './TaskDrawerSection';
import type { TaskDrawerDescriptionProps } from './types.d';
import { PiTextAlignLeft } from 'react-icons/pi';

import { CancelButton, SaveButton } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import { useInlineEdit } from '@salvon/hooks/useInlineEdit';
import { useIsDarkMode } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { voc } from '@salvon/utils/object';

export default function TaskDrawerDescription({
  value = '',
  editable = true,
  onSave,
  title,
  icon = <PiTextAlignLeft size={15} />,
  placeholder,
  defaultOpen = true,
  renderValue,
  renderEditor,
  open,
  setOpen,
}: TaskDrawerDescriptionProps) {
  const t = useTranslation();
  const isDark = useIsDarkMode();

  const edit = useInlineEdit(value, onSave);
  const empty = !value || value.trim().length === 0;

  return (
    <TaskDrawerSection
      title={title ?? t('description')}
      icon={icon}
      defaultOpen={defaultOpen}
      open={open}
      setOpen={setOpen}
      loading={edit.loading}
    >
      {edit.editing ? (
        <Flex
          column
          fw
          sx={{
            gap: 1.5,
            pointerEvents: edit.loading ? 'none' : 'auto',
            opacity: edit.loading ? 0.6 : 1,
          }}
        >
          {renderEditor ? (
            renderEditor({
              value: edit.value,
              setValue: edit.setValue,
              save: edit.save,
              cancel: edit.cancel,
              loading: edit.loading,
            })
          ) : (
            <TextField
              value={edit.value}
              autoFocus
              multiline
              minRows={3}
              fullWidth
              placeholder={
                typeof placeholder === 'string' ? placeholder : undefined
              }
              onChange={(event) => edit.setValue(event.target.value)}
              sx={{ '& .MuiOutlinedInput-root': { borderRadius: 2 } }}
            />
          )}
          <Flex jEnd sx={{ gap: 1 }}>
            <CancelButton
              variant="text"
              color="inherit"
              size="small"
              onClick={edit.cancel}
            />
            <SaveButton
              variant="contained"
              size="small"
              onClick={edit.save}
              loading={edit.loading}
            />
          </Flex>
        </Flex>
      ) : (
        <Flex
          fw
          onClick={() => editable && edit.begin()}
          sx={{
            borderRadius: '9px',
            px: 1,
            py: 0.5,
            ...voc(editable, {
              cursor: 'text',
              '&:hover': { background: isDark ? '#222' : '#eee' },
            }),
          }}
        >
          {empty ? (
            <Typography
              sx={{ fontSize: 14, fontStyle: 'italic', color: 'text.disabled' }}
            >
              {placeholder ?? t('none')}
            </Typography>
          ) : renderValue ? (
            renderValue(value)
          ) : (
            <Typography sx={{ fontSize: 14, whiteSpace: 'pre-wrap' }}>
              {value}
            </Typography>
          )}
        </Flex>
      )}
    </TaskDrawerSection>
  );
}
