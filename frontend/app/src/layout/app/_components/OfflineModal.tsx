import { useEffect, useRef, useState } from 'react';
import { PiWifiHigh, PiWifiSlash } from 'react-icons/pi';

import { Div, Flex } from '@salvon/components/div';
import { Modal } from '@salvon/components/modal';
import { useOnline } from '@salvon/hooks/useOnline';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifySuccess } from '@salvon/utils/notify';

import SheepRun from '@app/layout/_shared/guest/_components/SheepRun';

/* connection dropped — tell the user and give them something to do while waiting */
export default function OfflineModal() {
  const t = useTranslation();
  const online = useOnline();
  const [open, setOpen] = useState(false);
  const wasOffline = useRef(false);

  useEffect(() => {
    if (!online) {
      wasOffline.current = true;
      setOpen(true);
      return;
    }

    if (wasOffline.current) {
      wasOffline.current = false;
      notifySuccess(t('back_online'));
    }
  }, [online]);

  return (
    <Modal
      open={open}
      setOpen={setOpen}
      maxWidth="md"
      closeButton
      closeOnEsc
      title={
        <Flex
          aCenter
          sx={{ gap: '8px', color: online ? '#339138' : '#b3562d' }}
        >
          {online ? <PiWifiHigh size={20} /> : <PiWifiSlash size={20} />}
          {online ? t('back_online') : t('offline')}
        </Flex>
      }
    >
      <Div sx={{ fontSize: '14px', color: '#5c6b6a', margin: '12px 0 16px' }}>
        {online ? t('back_online_description') : t('offline_description')}
      </Div>

      <SheepRun height={400} scale={1.5} />
    </Modal>
  );
}
