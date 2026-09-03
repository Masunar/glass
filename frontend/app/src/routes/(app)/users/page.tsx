import Heading from '@app-components/Heading';
import { appRoutes } from '@router/app-router';

import columns from './_components/columns';
import Add from './_components/modal/Add';
import { useRef } from 'react';
import { PiUserCircleGear } from 'react-icons/pi';

import { Card } from '@salvon/components/card';
import { Flex } from '@salvon/components/div';
import ApiTable, {
  type ApiTableRef,
} from '@salvon/components/legacy/table/ApiTable';
import { useTranslation } from '@salvon/hooks/useTranslation';

import { UsersApi } from '@app/api/UsersApi';

export default function Page() {
  const ref = useRef<ApiTableRef>(null);
  const t = useTranslation();

  const reload = () => {
    ref.current?.reloadTable();
  };

  return (
    <div className="ge-boxed">
      <Flex column gap={2}>
        <Heading
          returnTo={{
            path: appRoutes.index,
          }}
          icon={<PiUserCircleGear />}
          title={t('page.users.title')}
        >
          <Add reload={reload} />
        </Heading>
        <Card>
          <ApiTable ref={ref} columns={columns} apiClass={UsersApi} />
        </Card>
      </Flex>
    </div>
  );
}
