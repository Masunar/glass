import GroupLabel from './GroupLabel';
import { useState } from 'react';
import {
  BsAndroid,
  BsApple,
  BsBrowserChrome,
  BsBrowserFirefox,
  BsWindows,
} from 'react-icons/bs';
import { MdPeople, MdSmartButton, MdToggleOn } from 'react-icons/md';

import { Button } from '@salvon/components/button';
import { Card } from '@salvon/components/card';
import { Flex } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';
import ToggleGroup from '@salvon/components/toggle-group/ToggleGroup';
import { useConfirmModal } from '@salvon/hooks/useConfirmModal';
import { useDrawer } from '@salvon/hooks/useDrawer';
import { useModal } from '@salvon/hooks/useModal';

const toggleOptions = [
  { value: 1, label: <BsApple /> },
  { value: 2, label: <BsAndroid /> },
  { value: 3, label: <BsWindows /> },
  { value: 4, label: <BsBrowserChrome /> },
  { value: 5, label: <BsBrowserFirefox /> },
];

export default function Buttons() {
  const [popoverLoading, setPopoverLoading] = useState(false);
  const confirm = useConfirmModal();

  const [openDrawer] = useDrawer();
  const [openModal] = useModal();

  return (
    <>
      <Card
        fw
        heading={{
          icon: <MdSmartButton />,
          title: 'Button',
          subtitle: 'Warianty semantyczne z ikonami',
        }}
      >
        <Flex column gap={2.5}>
          <Flex gap={1} wrap>
            <Button>default</Button>
            <Button preset="add" />
            <Button preset="cancel" />
            <Button preset="delete" />
            <Button preset="edit" />
            <Button preset="open" />
            <Button preset="return" />
            <Button preset="save" />
          </Flex>

          <Flex column gap={1}>
            <GroupLabel>Functional addons</GroupLabel>
            <Flex gap={1} wrap>
              <Button onClick={() => openDrawer({ content: <TestCounter /> })}>
                Default with drawer
              </Button>
              <Button
                onClick={() =>
                  openModal({
                    content: <TestCounter />,
                    config: {
                      closeOnEsc: true,
                      closeOnBackdropClick: true,
                    },
                  })
                }
              >
                Default with modal
              </Button>
              <Button
                onClick={() =>
                  confirm({
                    title: 'Are you sure?',
                    autoCloseOnConfirm: false,
                    onConfirm: ({ closeModal, setLoading }) => {
                      setLoading(true);
                      setTimeout(() => {
                        console.log('ok click');
                        setLoading(false);
                        closeModal();
                      }, 4000);
                    },
                  })
                }
              >
                Default with modal confirm
              </Button>
              <Button
                confirm={{
                  preset: 'delete',
                  title: 'Usunąć wpis?',
                  description: 'Tej operacji nie można cofnąć.',
                  confirmTitle: 'Tak, usuń',
                  placement: 'top-center',
                  loading: popoverLoading,
                  onConfirm: (closePopover) => {
                    setPopoverLoading(true);
                    setTimeout(() => {
                      setPopoverLoading(false);
                      closePopover();
                    }, 5000);
                  },
                }}
                preset="delete"
              >
                Delete with confirm popover
              </Button>
            </Flex>
          </Flex>

          <Flex column gap={1}>
            <GroupLabel>IconButton</GroupLabel>
            <Flex gap={1} wrap>
              <IconButton icon={<MdPeople />} />
              <IconButton preset="add" />
              <IconButton preset="cancel" />
              <IconButton
                confirm={{
                  preset: 'delete',
                  title: 'Usunąć wpis?',
                  description: 'Tej operacji nie można cofnąć.',
                  confirmTitle: 'Tak, usuń',
                }}
                preset="delete"
              />
              <IconButton preset="edit" />
              <IconButton preset="open" />
              <IconButton preset="return" />
              <IconButton preset="save" />
            </Flex>
          </Flex>
        </Flex>
      </Card>

      <Card
        fw
        heading={{
          icon: <MdToggleOn />,
          title: 'Toggle buttons group',
          subtitle: 'Wybór pojedynczy i wielokrotny, rozmiar mały i średni',
        }}
      >
        <Flex gap={3} wrap>
          <Flex column gap={1}>
            <GroupLabel>Single · small</GroupLabel>
            <ToggleGroup options={toggleOptions} />
          </Flex>
          <Flex column gap={1}>
            <GroupLabel>Multiple · medium</GroupLabel>
            <ToggleGroup multiple size="medium" options={toggleOptions} />
          </Flex>
        </Flex>
      </Card>
    </>
  );
}

const TestCounter = () => {
  const [count, setCount] = useState(0);

  return (
    <Flex center gap={1} column p={2}>
      <div>Total count: {count}</div>
      <Button onClick={() => setCount(count + 1)}>+1</Button>
    </Flex>
  );
};
