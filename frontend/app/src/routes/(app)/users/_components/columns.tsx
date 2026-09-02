import Delete from './Delete';
import Edit from './modal/Edit';

import type { Column } from '@salvon/components/legacy/table/Interfaces';

import { formatDate } from '@app/components/utils/format-utils';
import { userName } from '@app/components/utils/user';

const columns: Array<Column> = [
  {
    name: 'id',
    label: 'id',
  },
  {
    name: 'name',
    label: 'name',
    render: ({ row }) => userName(row),
  },
  {
    name: 'email',
    label: 'email',
  },
  {
    name: 'phone',
    label: 'phone',
  },
  {
    name: 'last_login_at',
    label: 'last_login_at',
    render: ({ row }) => formatDate(row.last_login_at),
  },
  {
    name: 'role.name',
    label: 'role',
    render: ({ row }) =>
      row.roles?.map((r: any) => <div key={r.id ?? r.name}>{r.name}</div>),
  },
  {
    name: 'is_active',
    label: 'is_active',
  },
  {
    name: 'actions',
    label: 'actions',
    align: 'right',
    actions: [Edit, Delete],
    headingCellProps: {
      sx: {
        width: '40px',
      },
    },
  },
];

export default columns;
