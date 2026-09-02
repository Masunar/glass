import type { QuickAction } from '../types.d';
import type { Dayjs } from 'dayjs';

import { Button } from '@salvon/components/button';
import { Div, Flex } from '@salvon/components/div';

type CalendarFooterProps = {
  summary: string;
  quickActions?: QuickAction[];
  value: Dayjs | null;
  clearLabel: string;
  applyLabel: string;
  onQuickAction: (action: QuickAction) => void;
  onClear: () => void;
  onApply: () => void;
};

export default function CalendarFooter({
  summary,
  quickActions,
  value,
  clearLabel,
  applyLabel,
  onQuickAction,
  onClear,
  onApply,
}: CalendarFooterProps) {
  return (
    <Div
      sx={{ pt: 1.5, mt: 1.5, borderTop: '1px solid', borderColor: 'divider' }}
    >
      {quickActions && quickActions.length > 0 && (
        <Flex gap={1} wrap mb={1.5}>
          {quickActions.map((a, i) => (
            <Button
              key={i}
              size="small"
              variant="outlined"
              color="inherit"
              onClick={() => onQuickAction(a)}
              sx={{
                borderRadius: '999px',
                borderColor: 'divider',
                color: 'text.primary',
              }}
            >
              {a.label}
            </Button>
          ))}
        </Flex>
      )}

      <Flex jBetween aCenter gap={1}>
        <Div
          sx={{ fontSize: '0.85rem', color: 'text.secondary', fontWeight: 500 }}
        >
          {summary}
        </Div>
        <Flex gap={1}>
          <Button
            size="small"
            variant="outlined"
            color="inherit"
            onClick={onClear}
            sx={{ borderColor: 'divider', color: 'text.primary' }}
          >
            {clearLabel}
          </Button>
          <Button size="small" onClick={onApply}>
            {applyLabel}
          </Button>
        </Flex>
      </Flex>
    </Div>
  );
}
