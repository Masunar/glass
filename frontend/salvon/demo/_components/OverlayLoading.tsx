import GroupLabel from './GroupLabel';
import { useState } from 'react';
import { MdHourglassEmpty } from 'react-icons/md';

import { Button } from '@salvon/components/button';
import { Card } from '@salvon/components/card';
import { Flex } from '@salvon/components/div';
import { OverlayLoading as OverlayLoadingComponent } from '@salvon/components/progress';

export default function OverlayLoading() {
  const [loading, setLoading] = useState(false);

  const trigger = () => {
    setLoading(true);
    setTimeout(() => setLoading(false), 3000);
  };

  return (
    <Card
      fw
      heading={{
        icon: <MdHourglassEmpty />,
        title: 'Overlay loading',
        subtitle: 'Nakładka ładowania nad opakowaną zawartością',
      }}
    >
      <Flex column gap={2}>
        <Button onClick={trigger}>Trigger loading (3s)</Button>

        <Flex column gap={1}>
          <GroupLabel>Wrapped content</GroupLabel>
          <OverlayLoadingComponent loading={loading} tip="Ładowanie...">
            <Flex column gap={1} p={2}>
              <div>Some wrapped content that gets blocked while loading.</div>
              <Button>Not clickable while loading</Button>
            </Flex>
          </OverlayLoadingComponent>
        </Flex>
      </Flex>
    </Card>
  );
}
