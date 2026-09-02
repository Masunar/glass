import { CgMoveRight } from 'react-icons/cg';

import { Div } from '@salvon/components/div';
import { BaseIconButton } from '@salvon/components/icon-button';
import { useMenuControl } from '@salvon/hooks/useMenuControl';
import { useTranslation } from '@salvon/hooks/useTranslation';

export type MobileMenuToggleProps = {
  ripple?: boolean;
};

export default function MobileMenuToggle({
  ripple = false,
}: MobileMenuToggleProps) {
  const { showMobile } = useMenuControl();
  const t = useTranslation();

  return (
    <Div sx={{ display: { xs: 'block', md: 'none' } }}>
      <BaseIconButton
        onClick={showMobile}
        label={t('show_menu')}
        disableRipple={!ripple}
      >
        <CgMoveRight />
      </BaseIconButton>
    </Div>
  );
}
