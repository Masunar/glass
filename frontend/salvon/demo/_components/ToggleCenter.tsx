import { Typography } from '@mui/material';

import { useState } from 'react';
import { FiInfo } from 'react-icons/fi';
import {
  HiOutlineArrowUpTray,
  HiOutlineArrowUturnLeft,
  HiOutlineEye,
  HiOutlineListBullet,
  HiOutlinePencilSquare,
  HiOutlinePlusCircle,
  HiOutlineTrash,
} from 'react-icons/hi2';
import { LuShieldCheck } from 'react-icons/lu';
import {
  TbCoin,
  TbCurrencyDollar,
  TbFolder,
  TbMail,
  TbTag,
  TbTags,
  TbTruck,
  TbUsers,
  TbUsersGroup,
} from 'react-icons/tb';

import { Button } from '@salvon/components/button';
import { Card } from '@salvon/components/card';
import {
  ToggleCenter,
  type ToggleCenterValue,
  type ToggleGroup,
} from '@salvon/components/toggle-center';

const crud = () => [
  {
    id: 'list',
    label: 'Listowanie',
    description: 'Widok listy rekordów modułu',
    icon: <HiOutlineListBullet />,
  },
  {
    id: 'read',
    label: 'Odczyt',
    description: 'Podgląd szczegółów rekordu',
    icon: <HiOutlineEye />,
  },
  {
    id: 'create',
    label: 'Tworzenie',
    description: 'Dodawanie nowych rekordów',
    icon: <HiOutlinePlusCircle />,
  },
  {
    id: 'update',
    label: 'Edycja',
    description: 'Zmiana istniejących rekordów',
    icon: <HiOutlinePencilSquare />,
  },
  {
    id: 'delete',
    label: 'Usuwanie',
    description: 'Przenoszenie rekordów do kosza',
    icon: <HiOutlineTrash />,
  },
  {
    id: 'restore',
    label: 'Przywracanie',
    description: 'Odzyskiwanie usuniętych rekordów',
    icon: <HiOutlineArrowUturnLeft />,
  },
  {
    id: 'export',
    label: 'Eksport',
    description: 'Pobieranie danych do pliku',
    icon: <HiOutlineArrowUpTray />,
  },
];

const footer = (
  <Typography
    variant="body2"
    color="text.disabled"
    sx={{ display: 'flex', gap: 1, alignItems: 'center' }}
  >
    <FiInfo />
    Eksport, import i zarządzanie pojawiają się tylko w modułach, które je
    obsługują.
  </Typography>
);

const groups: ToggleGroup[] = [
  {
    id: 'attr-values',
    label: 'Wartości atrybutów',
    section: 'KATALOG',
    icon: <TbTag />,
    options: crud(),
  },
  {
    id: 'attributes',
    label: 'Atrybuty',
    section: 'KATALOG',
    icon: <TbTag />,
    options: crud(),
  },
  {
    id: 'availability',
    label: 'Statusy dostępności',
    section: 'KATALOG',
    icon: <TbTags />,
    options: crud(),
  },
  {
    id: 'collections',
    label: 'Kolekcje',
    section: 'KATALOG',
    icon: <TbFolder />,
    options: crud(),
  },
  {
    id: 'clients',
    label: 'Klienci',
    section: 'SPRZEDAŻ',
    icon: <TbUsers />,
    options: crud(),
  },
  {
    id: 'client-groups',
    label: 'Grupy klientów',
    section: 'SPRZEDAŻ',
    icon: <TbUsersGroup />,
    options: crud(),
  },
  {
    id: 'currencies',
    label: 'Waluty',
    section: 'SPRZEDAŻ',
    icon: <TbCurrencyDollar />,
    options: crud(),
  },
  {
    id: 'rates',
    label: 'Kursy walut',
    section: 'SPRZEDAŻ',
    icon: <TbCoin />,
    options: crud(),
  },
  {
    id: 'blog-cats',
    label: 'Kategorie bloga',
    section: 'TREŚCI',
    icon: <TbFolder />,
    options: crud(),
  },
  {
    id: 'blog-posts',
    label: 'Wpisy blogowe',
    section: 'TREŚCI',
    icon: <TbTag />,
    options: crud(),
  },
  {
    id: 'delivery-texts',
    label: 'Teksty dostawy',
    section: 'TREŚCI',
    icon: <TbTruck />,
    options: crud(),
  },
  {
    id: 'email-templates',
    label: 'Szablony e-mail',
    section: 'TREŚCI',
    icon: <TbMail />,
    options: crud(),
  },
];

const defaultValue: ToggleCenterValue = {
  'attr-values': { list: true, read: true },
  attributes: {
    list: true,
    read: true,
    create: true,
    update: true,
    delete: true,
    restore: true,
    export: true,
  },
  availability: { list: true, read: true },
  clients: { list: true, read: true, create: true, update: true },
  'client-groups': { list: true, read: true },
  'blog-cats': { list: true, read: true },
  'blog-posts': {
    list: true,
    read: true,
    create: true,
    update: true,
    delete: true,
  },
};

export default function ToggleCenter_() {
  const [value, setValue] = useState<ToggleCenterValue>(defaultValue);

  return (
    <>
      <Card
        fw
        heading={{
          icon: <LuShieldCheck />,
          title: 'ToggleCenter',
          subtitle:
            'SettingsCenter z panelem przełączników — uprawnienia per moduł, wyszukiwanie i liczniki nadanych praw',
          end: (
            <Button
              onClick={() => {
                console.log(value);
              }}
            >
              Console log
            </Button>
          ),
        }}
        slotProps={{
          body: {
            sx: {
              padding: 0,
            },
          },
        }}
      >
        <ToggleCenter
          height={700}
          groups={groups}
          value={value}
          onChange={(all) => {
            setValue(all);
          }}
          footer={footer}
          labels={{
            searchPlaceholder: 'Szukaj modułu...',
            granted: (g, t) => `${g} z ${t} uprawnień nadanych`,
          }}
        />
      </Card>
    </>
  );
}
