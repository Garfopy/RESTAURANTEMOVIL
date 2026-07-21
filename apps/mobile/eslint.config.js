const { defineConfig } = require('eslint/config');
const expoConfig = require('eslint-config-expo/flat');

module.exports = defineConfig([
  expoConfig,
  {
    ignores: ['.expo/**', 'android/**', 'assets/**', 'config.json'],
    settings: {
      'import/resolver': {
        typescript: {
          project: './tsconfig.json',
        },
      },
    },
    rules: {
      // TypeScript resolves the workspace aliases; the import rule walks above
      // the OneDrive workspace on Windows and fails on protected directories.
      'import/no-unresolved': 'off',
      // These React Compiler rules do not understand React Native Animated,
      // Reanimated shared values or modal state synchronization yet.
      'react-hooks/error-boundaries': 'off',
      'react-hooks/immutability': 'off',
      'react-hooks/preserve-manual-memoization': 'off',
      'react-hooks/purity': 'off',
      'react-hooks/refs': 'off',
      'react-hooks/set-state-in-effect': 'off',
    },
  },
]);
