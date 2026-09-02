import js from '@eslint/js';

// import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
  { ignores: ['dist'] },
  {
    extends: [js.configs.recommended, ...tseslint.configs.recommended],
    files: ['**/*.{ts,tsx}'],
    languageOptions: {
      ecmaVersion: 2022,
      globals: globals.browser,
    },
    plugins: {
      // 'react-hooks': reactHooks,
      'react-refresh': reactRefresh,
    },
    rules: {
      // ...reactHooks.configs.recommended.rules,
      'react-refresh/only-export-components': 'off',
      '@typescript-eslint/no-explicit-any': 'off',
      '@typescript-eslint/ban-ts-comment': 'off',
      // Regula react-hooks jest tu wylaczona od zawsze, ale sama wtyczka
      // nie jest zainstalowana (import wyzej zakomentowany), wiec ESLint
      // przewracal sie na komentarzach eslint-disable odwolujacych sie do
      // nieistniejacej reguly. Do domkniecia: dolozyc wtyczke i wlaczyc.
    },
  },
);
