import { Typography } from '@mui/material';

import { MdNotifications } from 'react-icons/md';
import { PiInfoFill } from 'react-icons/pi';

import { Button, NotificationButton } from '@salvon/components/button';
import { Card } from '@salvon/components/card';
import { Div, Flex } from '@salvon/components/div';
import {
  notify,
  notifyError,
  notifyInfo,
  notifySuccess,
  notifyWarning,
} from '@salvon/utils/notify';
import { toast } from '@salvon/utils/toast';

type Variant = 'base' | 'alternative';

const LOREM = 'Lorem ipsum dolor sit amet, consectetur adipiscing.';

const infoIcon = {
  icon: <PiInfoFill />,
  color: '#fff',
  bgcolor: 'info.main',
};

const presets = [
  { variant: 'success', trigger: notifySuccess },
  { variant: 'error', trigger: notifyError },
  { variant: 'warning', trigger: notifyWarning },
  { variant: 'info', trigger: notifyInfo },
];

const footerActions = ({ close }: { close: () => void }) => (
  <Flex gap={1}>
    <NotificationButton color="primary">Accept</NotificationButton>
    <NotificationButton color="secondary" onClick={close}>
      Reject
    </NotificationButton>
  </Flex>
);

const rightBarAction = ({ close }: { close: () => void }) => (
  <NotificationButton color="primary" onClick={close}>
    Odśwież
  </NotificationButton>
);

const baseCustoms = [
  {
    label: 'no icon',
    run: () =>
      notify({
        variant: 'base',
        message: 'To jest info',
        description: LOREM,
        accentColor: 'info.main',
      }),
  },
  {
    label: 'no accent',
    run: () =>
      notify({
        variant: 'base',
        message: 'To jest info',
        description: LOREM,
        icon: infoIcon,
        accentBar: false,
      }),
  },
  {
    label: 'footer',
    run: () =>
      notify({
        variant: 'base',
        message: 'To jest info',
        description: LOREM,
        accentColor: 'info.main',
        icon: infoIcon,
        footer: footerActions,
      }),
  },
  {
    label: 'no accent no icon',
    run: () =>
      notify({
        variant: 'base',
        message: 'To jest info',
        description: LOREM,
        accentBar: false,
      }),
  },
  {
    label: 'autoclose undefined',
    run: () =>
      notify({
        variant: 'base',
        message: 'To jest info',
        description: LOREM,
        accentColor: 'info.main',
        icon: infoIcon,
        autoclose: undefined,
      }),
  },
  {
    label: 'autoclose infinite',
    run: () =>
      notify({
        variant: 'base',
        message: 'To jest info',
        description: LOREM,
        accentColor: 'info.main',
        icon: infoIcon,
        autoclose: Infinity,
      }),
  },
];

const altCustoms = [
  {
    label: 'footer',
    run: () =>
      notify({
        message: 'To jest info',
        description: LOREM,
        accentColor: 'info.main',
        icon: infoIcon,
        footer: footerActions,
      }),
  },
  {
    label: 'right bar',
    run: () =>
      notify({
        message: 'To jest info',
        description: LOREM,
        accentColor: 'info.main',
        icon: infoIcon,
        rightBar: rightBarAction,
      }),
  },
  {
    label: 'right bar + footer',
    run: () =>
      notify({
        message: 'To jest info',
        description: LOREM,
        accentColor: 'info.main',
        icon: infoIcon,
        rightBar: rightBarAction,
        footer: footerActions,
      }),
  },
  {
    label: 'without icon',
    run: () =>
      notify({
        message: 'To jest info',
        description: LOREM,
        accentColor: 'info.main',
      }),
  },
  {
    label: 'no time',
    run: () =>
      notify({
        message: 'To jest info',
        description: LOREM,
        accentColor: 'info.main',
        icon: infoIcon,
        time: undefined,
      }),
  },
  {
    label: 'bar on top',
    run: () =>
      notify({
        message: 'To jest info',
        description: LOREM,
        accentColor: 'info.main',
        icon: infoIcon,
        barPosition: 'top',
      }),
  },
  {
    label: 'infinite bar on top no icon',
    run: () =>
      notify({
        message: 'To jest info',
        description: LOREM,
        accentColor: 'info.main',
        barPosition: 'top',
        autoclose: Infinity,
      }),
  },
  {
    label: 'bar on top no desc',
    run: () =>
      notify({
        message: 'To jest info',
        accentColor: 'info.main',
        icon: infoIcon,
        barPosition: 'top',
      }),
  },
  {
    label: 'bar on bottom',
    run: () =>
      notify({
        message: 'To jest info',
        description: LOREM,
        accentColor: 'info.main',
        icon: infoIcon,
        barPosition: 'bottom',
      }),
  },
  {
    label: 'bar on bottom no desc',
    run: () =>
      notify({
        message: 'To jest info',
        accentColor: 'info.main',
        icon: infoIcon,
        barPosition: 'bottom',
      }),
  },
  {
    label: 'autoclose undefined',
    run: () =>
      notify({
        message: 'To jest info',
        description: LOREM,
        accentColor: 'info.main',
        icon: infoIcon,
        autoclose: undefined,
      }),
  },
  {
    label: 'autoclose infinite',
    run: () =>
      notify({
        message: 'To jest info',
        description: LOREM,
        accentColor: 'info.main',
        icon: infoIcon,
        autoclose: Infinity,
      }),
  },
];

function Column({ variant }: { variant: Variant }) {
  return (
    <Div sx={{ flex: 1, minWidth: 0 }}>
      <Typography variant="h6" sx={{ mb: 1.5, textTransform: 'capitalize' }}>
        {variant}
      </Typography>

      <Flex column gap={2}>
        <Div>
          <Typography variant="body2" sx={{ mb: 0.5, color: 'text.secondary' }}>
            Message only
          </Typography>
          <Flex gap={1} wrap>
            {presets.map((p) => (
              <Button
                key={p.variant}
                onClick={() =>
                  p.trigger('To jest ' + p.variant, undefined, { variant })
                }
              >
                {p.variant}
              </Button>
            ))}
          </Flex>
        </Div>

        <Div>
          <Typography variant="body2" sx={{ mb: 0.5, color: 'text.secondary' }}>
            With description
          </Typography>
          <Flex gap={1} wrap>
            {presets.map((p) => (
              <Button
                key={p.variant}
                onClick={() =>
                  p.trigger('To jest ' + p.variant, LOREM, { variant })
                }
              >
                {p.variant}
              </Button>
            ))}
          </Flex>
        </Div>

        <Div>
          <Typography variant="body2" sx={{ mb: 0.5, color: 'text.secondary' }}>
            Advanced configurations
          </Typography>
          <Flex gap={1} wrap>
            {(variant === 'base' ? baseCustoms : altCustoms).map((c) => (
              <Button key={c.label} onClick={c.run}>
                {c.label}
              </Button>
            ))}
          </Flex>
        </Div>
      </Flex>
    </Div>
  );
}

/** Fully custom toast — render your own node, handle id/onClose yourself. */
const customNotify = () =>
  toast.custom((id) => (
    <Card sx={{ padding: 2 }}>
      <Typography sx={{ fontWeight: 700 }}>Custom node</Typography>
      <Typography variant="body2" sx={{ color: 'text.secondary' }}>
        Dowolny <b>ReactNode</b> — nie tylko tekst.
      </Typography>
      <Button sx={{ mt: 1 }} onClick={() => toast.dismiss(id)}>
        Zamknij
      </Button>
    </Card>
  ));

type Props = {};
export default function Notifications({}: Props) {
  return (
    <Card
      fw
      heading={{
        icon: <MdNotifications />,
        title: 'Notification',
        subtitle: 'Wyzwalacze toastów — base vs alternative',
      }}
    >
      <Flex gap={3} align="flex-start" wrap>
        <Column variant="base" />
        <Column variant="alternative" />
      </Flex>

      <Div sx={{ mt: 3 }}>
        <Typography variant="body2" sx={{ mb: 0.5, color: 'text.secondary' }}>
          Custom notification render
        </Typography>
        <Button onClick={customNotify}>custom</Button>
      </Div>
    </Card>
  );
}
