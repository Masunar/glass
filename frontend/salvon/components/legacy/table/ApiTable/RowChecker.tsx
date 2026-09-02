import { Checkbox } from '@mui/material';

import { useTableSelectionManipulator } from '../hooks/useTableSelectionManipulator';

import { Switch } from '@salvon/components/switch';

export type CheckerType = 'checkbox' | 'switch' | 'android-switch';

export type RowCheckerProps = {
  row: any;
  componentProps?: {
    component?: CheckerType;
  };
  disabled?: boolean;
};

export default function RowChecker({
  row,
  componentProps = {},
  disabled,
}: RowCheckerProps) {
  const { isSelected, addSelection, removeSelection } =
    useTableSelectionManipulator();
  const { component } = componentProps;

  const renderProps = {
    checked: isSelected(row),
    onClick: (event: any) => {
      const checked = event.target.checked;

      if (checked) {
        addSelection(row);
        return;
      }

      removeSelection(row);
    },
    disabled: disabled,
  };

  if (component === 'switch') {
    return <Switch variant="mui" {...renderProps} />;
  }

  if (component === 'android-switch') {
    return <Switch variant="indicated" {...renderProps} />;
  }

  return <Checkbox {...renderProps} />;
}
