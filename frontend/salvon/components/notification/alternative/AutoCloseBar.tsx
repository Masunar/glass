import { Div } from '@salvon/components/div';
import { useTimeoutProgress } from '@salvon/hooks/useTimeoutProgress';

export type AutoCloseBarProps = {
  autoclose: number;
  onClose: () => void;
  color?: string;
  full?: boolean;
  noTrack?: boolean;
  width?: number;
};

export default function AutoCloseBar({
  autoclose,
  onClose,
  color,
  full = false,
  noTrack = false,
  width = 44,
}: AutoCloseBarProps) {
  const { progress: raw } = useTimeoutProgress(autoclose, {
    onComplete: onClose,
  });
  const progress = Number.isFinite(raw) ? raw : 100;

  return (
    <Div
      sx={{
        position: 'relative',
        width: full ? '100%' : width,
        height: full ? 3 : 5,
        borderRadius: full ? 0 : '999px',
        backgroundColor: noTrack ? 'transparent' : 'action.disabledBackground',
        overflow: 'hidden',
      }}
    >
      <Div
        sx={{
          position: 'absolute',
          top: 0,
          left: 0,
          height: '100%',
          width: `${progress}%`,
          borderRadius: full ? 0 : '999px',
          backgroundColor: color ?? 'primary.main',
        }}
      />
    </Div>
  );
}
