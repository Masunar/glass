import { Chip, styled } from '@mui/material';
import Typography from '@mui/material/Typography';

import { Link } from '@salvon/components/navigation';
import generatePath from '@salvon/utils/generate-path';

interface Props {
  url: string;
  routeParams?: any;
  queryParams?: any;
  label: string;
  withoutChip?: boolean;
}

export default function TableLink({
  url,
  routeParams,
  queryParams,
  label,
  withoutChip = false,
  ...params
}: Props) {
  const StyledChip = styled(Chip)({
    cursor: 'pointer',
  });

  const preparedUrl = generatePath(url, {
    route: routeParams,
    query: queryParams,
  });

  return (
    <Link href={preparedUrl} {...params}>
      {withoutChip ? (
        <Typography>{label}</Typography>
      ) : (
        <StyledChip clickable color="default" label={label} />
      )}
    </Link>
  );
}
