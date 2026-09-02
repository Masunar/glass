import { MdAccountTree } from 'react-icons/md';

import { Card } from '@salvon/components/card';
import { CategoryTree as SalvonCategoryTree } from '@salvon/components/category-tree';
import type { CategoryNode } from '@salvon/components/category-tree';
import {
  DeleteIconButton,
  EditIconButton,
  OpenIconButton,
} from '@salvon/components/icon-button';

const data: CategoryNode[] = [
  {
    id: '1',
    name: 'Electronics',
    children: [
      {
        id: '1-1',
        name: 'Phones',
        children: [
          { id: '1-1-1', name: 'Smartphones' },
          { id: '1-1-2', name: 'Accessories' },
        ],
      },
      { id: '1-2', name: 'Laptops' },
      { id: '1-3', name: '' },
    ],
  },
  {
    id: '2',
    name: 'Home',
    children: [
      { id: '2-1', name: 'Furniture' },
      { id: '2-2', name: 'Lighting', is_active: false },
    ],
  },
  { id: '3', name: 'Books' },
];

export default function CategoryTree() {
  return (
    <Card
      fw
      heading={{
        icon: <MdAccountTree />,
        title: 'CategoryTree',
        subtitle:
          'Drzewo kategorii — wyszukiwanie, zmiana kolejności i akcje na węźle',
      }}
    >
      <SalvonCategoryTree
        data={data}
        height={500}
        translations={{ search: 'Search', noName: '(no name)' }}
        actions={(node) => (
          <>
            <OpenIconButton onClick={() => console.log('open', node.id)} />
            <EditIconButton onClick={() => console.log('edit', node.id)} />
            <DeleteIconButton onClick={() => console.log('delete', node.id)} />
          </>
        )}
        onChange={(next) => console.log('change', next)}
      />
    </Card>
  );
}
