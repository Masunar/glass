import ListIcon from './ListIcon';
import { BsCircleFill } from 'react-icons/bs';

export default function DotIcon({ isActive }: { isActive?: boolean }) {
  return (
    <ListIcon
      sx={{
        marginRight: '0',
        fontSize: isActive ? '0.42rem' : '0.3rem',
      }}
    >
      <BsCircleFill />
    </ListIcon>
  );
}
