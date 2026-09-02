import Buttons from './_components/Buttons';
import CategoryTree from './_components/CategoryTree';
import ControlCards from './_components/ControlCards';
import DataHub from './_components/DataHub';
import FileUpload from './_components/FileUpload';
import Form from './_components/Form/Form';
import Kanban from './_components/Kanban';
import MiniCalendar from './_components/MiniCalendar';
import Modals from './_components/Modals';
import NotificationPlane from './_components/NotificationPlane';
import Notifications from './_components/Notifications';
import OverlayLoading from './_components/OverlayLoading';
import PageEditor from './_components/PageEditor';
import PriceSettlement from './_components/PriceSettlement';
import RichTextEditor from './_components/RichTextEditor';
import Scheduler from './_components/Scheduler';
import ToggleCenter from './_components/ToggleCenter';

import { Flex } from '@salvon/components/div';

export default function Demo() {
  return (
    <Flex fw mt={1} gap={2} column wrap>
      <FileUpload />
      <MiniCalendar />
      <Buttons />
      <Notifications />
      <OverlayLoading />
      <NotificationPlane />
      <Modals />
      <Form />
      <RichTextEditor />
      <PageEditor />
      <CategoryTree />
      <ControlCards />
      <Kanban />
      <PriceSettlement />
      <Scheduler />
      <DataHub />
      <ToggleCenter />
    </Flex>
  );
}
