import {
  IconButton,
  InputAdornment,
  type InputProps,
  type SxProps,
  TextField,
  Tooltip,
} from '@mui/material';

import {
  type ChangeEvent,
  forwardRef,
  useImperativeHandle,
  useState,
} from 'react';
import { MdClear, MdSearch } from 'react-icons/md';

import { useTranslation } from '@salvon/hooks/useTranslation';

interface Props {
  onSearch: (newValue: string) => void;
  label?: string;
  inputProps?: InputProps;
  sx?: SxProps;
}

export type SearchProps = Props;

export interface SearchRef {
  clearSearch: () => void;
  setDefaultValue: (value: string | Array<string>) => void;
  setValue: (value: string) => void;
}

const Search = forwardRef(({ label, onSearch, inputProps, sx }: Props, ref) => {
  const [value, setValue] = useState<string>('');
  const t = useTranslation();

  const handleInputChange = (event: ChangeEvent<HTMLInputElement>) => {
    const newValue = event.target.value;
    setValue(newValue);
  };

  const handleClear = () => {
    setValue('');
    onSearch(''); // Clear the parent's state
  };

  useImperativeHandle(ref, () => ({
    clearSearch: () => {
      handleClear();
    },
    setDefaultValue: (value: string) => {
      setValue(value);
    },
    setValue: (value: string) => {
      setValue(value);
    },
  }));

  const handleSearch = () => {
    onSearch(value);
  };

  return (
    <TextField
      value={value}
      onChange={handleInputChange}
      placeholder={label || t('search')}
      sx={{
        display: { md: 'flex' },
        minWidth: '300px',
        ...sx,
      }}
      onKeyDown={(event) => {
        if (event.key === 'Enter') {
          handleSearch();
        }
      }}
      slotProps={{
        input: {
          size: 'small',
          endAdornment: (
            <InputAdornment position="end" sx={{ mr: -1 }}>
              {value && (
                <Tooltip title={t('clear')} onClick={handleClear}>
                  <IconButton size="small">
                    <MdClear />
                  </IconButton>
                </Tooltip>
              )}
              <Tooltip title={t('search')}>
                <IconButton size="small" onClick={handleSearch}>
                  <MdSearch />
                </IconButton>
              </Tooltip>
            </InputAdornment>
          ),
          ...inputProps,
        },
      }}
    />
  );
});

export default Search;
