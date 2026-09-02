import {
  Tabs as MUITabs,
  type TabsProps as MUITabsProps,
  Tab,
} from '@mui/material';

import {
  type ForwardedRef,
  type ReactElement,
  useImperativeHandle,
} from 'react';

import { Div, type DivProps } from '@salvon/components/div';
import type { TabsItems } from '@salvon/components/tabs/types';
import { useSearchParamState } from '@salvon/hooks/useSearchParamState';
import type { SlotItem } from '@salvon/types';
import { voc } from '@salvon/utils/object';

export type TabsRef = ForwardedRef<{
  activeTab: string;
}>;

export type TabsProps = {
  items: TabsItems;
  keepMounted?: boolean;
  queryParam?: string | false;
  slotProps?: {
    container?: SlotItem<DivProps>;
    tabs?: SlotItem<MUITabsProps>;
    panelContainer?: SlotItem<DivProps>;
  };
  keepExistingSearchParams?: boolean;
  ref?: TabsRef;
};

export default function Tabs({
  items,
  slotProps,
  keepMounted = false,
  queryParam = 'active',
  keepExistingSearchParams = false,
  ref,
}: TabsProps) {
  const { container, tabs, panelContainer } = slotProps ?? {};
  const [activeTab, setActiveTab] = useSearchParamState(
    items[0].name ?? '',
    queryParam,
    keepExistingSearchParams,
  );

  useImperativeHandle(ref, () => ({
    activeTab,
  }));

  return (
    <Div {...container}>
      <MUITabs
        variant="scrollable"
        scrollButtons="auto"
        allowScrollButtonsMobile={true}
        {...tabs}
        sx={{
          borderBottom: 1,
          borderColor: 'divider',
          ...(tabs?.sx ?? {}),
        }}
        value={activeTab}
        onChange={(_e, v) => setActiveTab(v)}
      >
        {items.map((tab) => (
          <Tab key={tab.name} value={tab.name} label={tab.label} />
        ))}
      </MUITabs>

      <Div {...panelContainer}>
        {items.map((tab) => (
          <TabPanel
            key={tab.name}
            active={tab.name === activeTab}
            keepMounted={keepMounted}
          >
            {tab.content}
          </TabPanel>
        ))}
      </Div>
    </Div>
  );
}

type TabPanelProps = {
  children: ReactElement;
  active: boolean;
  keepMounted?: boolean;
};

function TabPanel({ children, active, keepMounted = false }: TabPanelProps) {
  if (!keepMounted && !active) {
    return <></>;
  }

  return (
    <Div
      sx={{
        ...voc(!active, { display: 'none !important' }),
      }}
    >
      {children}
    </Div>
  );
}
