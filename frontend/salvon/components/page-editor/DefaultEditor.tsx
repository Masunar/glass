import { Puck, blocksPlugin, outlinePlugin } from '@puckeditor/core';
import type { Viewports } from '@puckeditor/core';
import '@puckeditor/core/dist/index.css';

import { defaultPuckConfig } from './blocks';
import type { PageEditorProps } from './types.d';
import { MdFormatListBulleted, MdWidgets } from 'react-icons/md';

import { Div } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

const defaultViewports: Viewports = [
  { width: '100%', height: 'auto', icon: 'Monitor', label: 'Desktop' },
  { width: 768, height: 'auto', icon: 'Tablet', label: 'Tablet' },
  { width: 375, height: 'auto', icon: 'Smartphone', label: 'Mobile' },
];

export default function DefaultEditor({
  value,
  onChange,
  onPublish,
  headerTitle,
  height,
  overrides,
  plugins,
  slotProps,
  iframe = true,
  config = defaultPuckConfig,
  viewports = defaultViewports,
}: PageEditorProps) {
  const t = useTranslation();

  const editorPlugins = [
    blocksPlugin({
      label: t('page.puck.blocks'),
      icon: <MdWidgets size={22} />,
    }),
    outlinePlugin({
      label: t('page.puck.outline'),
      icon: <MdFormatListBulleted size={22} />,
    }),
    ...(plugins ?? []),
  ];

  return (
    <Div
      className="puck-editor-light"
      {...slotProps?.root}
      sx={{ ...slotProps?.root?.sx }}
    >
      <Puck
        config={config}
        data={value ?? {}}
        onChange={onChange}
        onPublish={onPublish}
        headerTitle={headerTitle}
        height={height}
        iframe={{ enabled: iframe }}
        overrides={overrides}
        plugins={editorPlugins}
        viewports={viewports}
        ui={{
          viewports: {
            current: {
              width: viewports[0].width,
              height: viewports[0].height ?? 'auto',
            },
            controlsVisible: true,
            options: [],
          },
        }}
        dictionary={{
          'header-publish': t('page.puck.publish'),
          'outline-header-title': t('page.puck.outline'),
        }}
      />
    </Div>
  );
}
