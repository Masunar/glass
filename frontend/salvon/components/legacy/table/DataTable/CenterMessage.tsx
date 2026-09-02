import { Typography } from '@mui/material';

import { Div } from '@salvon/components/div';

interface Props {
  text: string;
}

export default function CenterMessage({ text }: Props) {
  return (
    <Div>
      <Typography
        sx={{
          textAlign: 'center',
          pt: 8,
          pb: 6,
          width: '100%',
          fontSize: '1.2rem',
        }}
      >
        {text}
      </Typography>
    </Div>
  );
}
