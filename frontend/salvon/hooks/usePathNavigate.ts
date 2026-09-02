import { useNavigate as useRouterNavigate } from 'react-router';

import generatePath, {
  type GeneratePathParams,
  type GeneratePathUrl,
} from '@salvon/utils/generate-path';

export const usePathNavigate = () => {
  const navigate = useRouterNavigate();

  return (path: GeneratePathUrl, params?: GeneratePathParams) =>
    navigate(generatePath(path, params));
};

export const useNavigate = () => usePathNavigate();
