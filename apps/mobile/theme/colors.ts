// Paleta fija de UTEQ Cafetería. A propósito ya no se sincroniza con
// /settings/theme del panel admin — ver store/theme.store.ts.
export const DEFAULT_COLORS = {
  primary: '#A97C3F',
  primaryDark: '#8A5A2B',
  primaryLight: '#C4954F',
  accent: '#2B1B12',
  accentDark: '#1C110B',
  accentLight: '#5C4530',

  white: '#FFFFFF',
  background: '#FAECE0',
  button: '#A97C3F',
  buttonText: '#2B1B12',
  surface: '#FFFFFF',
  surfaceElevated: '#FAFAFA',

  text: '#2B1B12',
  textSecondary: '#6B5540',
  textMuted: '#8A7A65',
  textInverse: '#FFFFFF',

  border: '#E5E5EA',
  borderLight: '#F2F2F7',

  success: '#34C759',
  warning: '#FF9500',
  error: '#FF3B30',
  info: '#007AFF',

  muted: '#8E8E93',
  errorLight: '#FFF0EE',
  successLight: '#EDFBF0',
  warningLight: '#FFF7ED',
};

export const Colors = { ...DEFAULT_COLORS };

export type ColorKey = keyof typeof Colors;
export type ThemeColors = typeof Colors;
