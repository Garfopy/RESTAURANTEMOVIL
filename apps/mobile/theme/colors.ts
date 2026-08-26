// Paleta fija de UTEQ Cafetería (del moodboard: café oscuro, chocolate,
// caramelo, crema, verde). A propósito ya no se sincroniza con
// /settings/theme del panel admin — ver store/theme.store.ts.
export const DEFAULT_COLORS = {
  primary: '#2B1E14',
  primaryDark: '#1A120C',
  primaryLight: '#4A3524',
  accent: '#B48A5A',
  accentDark: '#8F6B3F',
  accentLight: '#D4B384',

  white: '#FFFFFF',
  background: '#F5EFE6',
  button: '#2B1E14',
  buttonText: '#FFFFFF',
  surface: '#FFFFFF',
  surfaceElevated: '#FBF8F3',

  text: '#2B1E14',
  textSecondary: '#6B4E37',
  textMuted: '#9A8672',
  textInverse: '#FFFFFF',

  border: '#E8DFD1',
  borderLight: '#F2ECE1',

  success: '#2E7D32',
  warning: '#FF9500',
  error: '#FF3B30',
  info: '#007AFF',

  muted: '#9A8672',
  errorLight: '#FFF0EE',
  successLight: '#E8F5E9',
  warningLight: '#FFF7ED',
};

export const Colors = { ...DEFAULT_COLORS };

export type ColorKey = keyof typeof Colors;
export type ThemeColors = typeof Colors;
