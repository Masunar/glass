import type { Data } from '@puckeditor/core';

import { useState } from 'react';
import { MdViewQuilt } from 'react-icons/md';

import { Card } from '@salvon/components/card';
import { PageEditor } from '@salvon/components/page-editor';

const initial: Data = {
  content: [
    {
      type: 'Heading',
      props: {
        id: 'h1',
        text: 'Strona demo',
        level: 'h2',
        align: 'left',
        size: 'lg',
        color: 'accent',
      },
    },
    {
      type: 'Text',
      props: {
        id: 't1',
        text: 'Przeciągaj bloki z lewego panelu.',
        align: 'left',
        size: 'md',
      },
    },
  ],
  root: { props: {} },
};

export default function PageEditorDemo() {
  const [data, setData] = useState<Data>(initial);

  return (
    <Card
      fw
      heading={{
        icon: <MdViewQuilt />,
        title: 'Edytor strony',
        subtitle: 'Puck Editor',
      }}
    >
      <PageEditor
        value={data}
        onChange={setData}
        onPublish={(d) => console.log('publish', d)}
        onPreview={(d) => console.log('preview', d)}
        height={600}
      />
    </Card>
  );
}
