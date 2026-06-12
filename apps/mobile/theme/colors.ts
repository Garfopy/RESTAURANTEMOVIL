export const Colors = {
  primary: '#1A1A2E',
  primaryDark: '#12122A',
  primaryLight: '#2A2A4E',
  accent: '#E8A020',
  accentDark: '#C8860C',
  accentLight: '#F5C060',

  white: '#FFFFFF',
  background: '#F5F5F7',
  surface: '#FFFFFF',
  surfaceElevated: '#FAFAFA',

  text: '#1A1A2E',
  textSecondary: '#636366',
  textMuted: '#8E8E93',
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
} as const;

export type ColorKey = keyof typeof Colors;
