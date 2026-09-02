import { Typography } from '@mui/material';

import FormModal from './Form/Modal';
import { type ReactNode } from 'react';
import {
  MdBolt,
  MdCropSquare,
  MdDescription,
  MdHelpOutline,
  MdWebAsset,
} from 'react-icons/md';

import { Button } from '@salvon/components/button';
import { Card } from '@salvon/components/card';
import { Div, Flex } from '@salvon/components/div';
import { ActionModal, ConfirmModal, Modal } from '@salvon/components/modal';

type Props = {};
export default function Modals({}: Props) {
  return (
    <Card
      fw
      heading={{
        icon: <MdWebAsset />,
        title: 'Modal',
        subtitle:
          'Okna dialogowe — podstawowy, potwierdzenie, akcja, formularz',
      }}
    >
      <Flex fw gap={2} wrap>
        <Tile icon={<MdCropSquare />} title="Modal">
          <Modal
            anchor={<Button>Modal</Button>}
            closeOnEsc
            closeOnBackdropClick
            closeButton
            title="Modal"
          >
            hi!
          </Modal>
        </Tile>
        <Tile icon={<MdHelpOutline />} title="Confirm Modal">
          <ConfirmModal
            anchor={<Button>Confirm Modal</Button>}
            closeButton
            title="Confirm modal"
          >
            Are you sure? This action can't be undone. Lorem ipsum
          </ConfirmModal>
        </Tile>
        <Tile icon={<MdBolt />} title="Action Modal">
          <ActionModal
            anchor={<Button>Action Modal</Button>}
            closeButton
            title="Action modal"
          />
        </Tile>
        <Tile icon={<MdDescription />} title="Form Modal">
          <FormModal />
        </Tile>
      </Flex>
    </Card>
  );
}

type TileProps = {
  icon: ReactNode;
  title: string;
  children: ReactNode;
};

const Tile = ({ icon, title, children }: TileProps) => (
  <Flex
    column
    gap={1.5}
    sx={(theme) => ({
      flex: '1 1 180px',
      minWidth: 180,
      p: 2,
      borderRadius: 2,
      border: `1px solid ${theme.palette.divider}`,
    })}
  >
    <Div sx={{ fontSize: 20, color: 'text.secondary', display: 'flex' }}>
      {icon}
    </Div>
    <Typography sx={{ fontWeight: 700 }}>{title}</Typography>
    {children}
  </Flex>
);
