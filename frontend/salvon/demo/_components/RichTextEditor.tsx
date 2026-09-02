import { useState } from 'react';
import { MdTextFields } from 'react-icons/md';

import { Card } from '@salvon/components/card';
import { RichTextEditor } from '@salvon/components/richtext-editor';

const initial =
  '<h2>Witaj w edytorze</h2><p>To jest <strong>bogaty</strong> edytor tekstu z <em>formatowaniem</em>.</p><ul><li>Lista punktowana</li><li>Przełącz na tryb <strong>HTML</strong>, aby zobaczyć źródło</li></ul>';

export default function RichTextEditorDemo() {
  const [value, setValue] = useState(initial);

  return (
    <Card
      fw
      heading={{
        icon: <MdTextFields />,
        title: 'Edytor tekstu',
        subtitle: 'Tiptap — formatowanie, nagłówki, listy, tryb HTML',
      }}
    >
      <RichTextEditor value={value} onChange={setValue} />
    </Card>
  );
}
