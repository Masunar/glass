import { FiTool } from 'react-icons/fi';
import { GrBook } from 'react-icons/gr';
import { HiOutlineDocumentText } from 'react-icons/hi2';
import { MdOutlineLocalOffer, MdOutlineVerified } from 'react-icons/md';
import { TbBuildingFactory2, TbTruckDelivery } from 'react-icons/tb';

import { Card } from '@salvon/components/card';
import { DataHub } from '@salvon/components/data-hub';
import type { DataHubCategory } from '@salvon/components/data-hub';

const categories: DataHubCategory[] = [
  {
    id: 'offers',
    label: 'Oferty i sprzedaż',
    accentColor: '#F59E0B',
    icon: { element: <MdOutlineLocalOffer /> },
    items: [
      { label: 'Status handlowca', onClick: () => {} },
      { label: 'Postęp oferty', onClick: () => {} },
      { label: 'Status odczytu oferty', onClick: () => {} },
      { label: 'Status rewizji', onClick: () => {} },
    ],
  },
  {
    id: 'contracts',
    label: 'Umowy',
    accentColor: '#3B82F6',
    icon: { element: <HiOutlineDocumentText /> },
    items: [
      { label: 'Szablon umowy', onClick: () => {} },
      { label: 'Typ transakcji', onClick: () => {} },
      { label: 'Typ numeracji', onClick: () => {} },
    ],
  },
  {
    id: 'work-orders',
    label: 'Zlecenia',
    accentColor: '#22C55E',
    icon: { element: <TbTruckDelivery /> },
    items: [
      { label: 'Ankieta zlecenia', onClick: () => {} },
      { label: 'Status zaopatrzenia', onClick: () => {} },
      { label: 'Status produkcji', onClick: () => {} },
    ],
  },
  {
    id: 'production',
    label: 'Produkcja',
    accentColor: '#A855F7',
    icon: { element: <TbBuildingFactory2 /> },
    items: [
      { label: 'Status produkcji', onClick: () => {} },
      { label: 'Status wyrobu', onClick: () => {} },
    ],
  },
  {
    id: 'service',
    label: 'Serwis',
    accentColor: '#EF4444',
    icon: { element: <FiTool /> },
    items: [
      { label: 'Status serwisu', onClick: () => {} },
      { label: 'Status naprawy', onClick: () => {} },
      { label: 'Zakres usług', onClick: () => {} },
    ],
  },
  {
    id: 'warranties',
    label: 'Gwarancje',
    accentColor: '#0D9488',
    icon: { element: <MdOutlineVerified />, color: '#fff', bgColor: '#0D9488' },
    items: [
      { label: 'Szablon gwarancji', onClick: () => {} },
      { label: 'Certyfikaty', onClick: () => {} },
    ],
  },
];

export default function DataHub_() {
  return (
    <Card
      fw
      heading={{
        icon: <GrBook />,
        title: 'DataHub',
        subtitle:
          'DataHub słowników — kafle kategorii, wyszukiwanie globalne i drill-down do kategorii',
      }}
    >
      <DataHub
        categories={categories}
        heading={{ icon: <GrBook />, title: 'Słowniki' }}
        labels={{
          count: (n) => `${n} słowników`,
          summary: (cats, items) => `${items} słowników w ${cats} kategoriach`,
          searchPlaceholder: 'Szukaj słownika…',
          searchInCategory: 'Szukaj w kategorii…',
        }}
      />
    </Card>
  );
}
