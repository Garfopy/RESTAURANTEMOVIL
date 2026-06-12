export const DEFAULT_COLORS = {
  primary: '#1A1A2E',
  primaryDark: '#12122A',
  primaryLight: '#2A2A4E',
  accent: '#E8A020',
  accentDark: '#C8860C',
  accentLight: '#F5C060',

  white: '#FFFFFF',
  background: '#F5F5F7',
  button: '#1A1A2E',
  buttonText: '#FFFFFF',
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
};

export const Colors = { ...DEFAULT_COLORS };

export type ColorKey = keyof typeof Colors;
export type ThemeColors = typeof Colors;

export interface ThemeColorSettings {
  primary: string;
  secondary: string;
  background: string;
  button: string;
  buttonText: string;
}

export function applyThemeColors(settings: Partial<ThemeColorSettings>): ThemeColors {
  const primary = normalizeHex(settings.primary) ?? DEFAULT_COLORS.primary;
  const secondary = normalizeHex(settings.secondary) ?? DEFAULT_COLORS.accent;
  const background = normalizeHex(settings.background) ?? DEFAULT_COLORS.background;
  const button = normalizeHex(settings.button) ?? primary;
  const buttonText = normalizeHex(settings.buttonText) ?? DEFAULT_COLORS.buttonText;

  Colors.primary = primary;
  Colors.primaryDark = mixHex(primary, '#000000', 0.18);
  Colors.primaryLight = mixHex(primary, '#FFFFFF', 0.22);
  Colors.accent = secondary;
  Colors.accentDark = mixHex(secondary, '#000000', 0.18);
  Colors.accentLight = mixHex(secondary, '#FFFFFF', 0.35);
  Colors.background = background;
  Colors.button = button;
  Colors.buttonText = buttonText;

  return { ...Colors };
}

export function normalizeHex(value?: string | null): string | undefined {
  if (!value || !/^#[0-9A-Fa-f]{6}$/.test(value)) return undefined;
  return value.toUpperCase();
}

function mixHex(from: string, to: string, amount: number): string {
  const source = hexToRgb(from);
  const target = hexToRgb(to);

  const mixed = source.map((channel, index) =>
    Math.round(channel + (target[index] - channel) * amount)
  );

  return rgbToHex(mixed[0], mixed[1], mixed[2]);
}

function hexToRgb(hex: string): [number, number, number] {
  return [
    parseInt(hex.slice(1, 3), 16),
    parseInt(hex.slice(3, 5), 16),
    parseInt(hex.slice(5, 7), 16),
  ];
}

function rgbToHex(r: number, g: number, b: number): string {
  return `#${[r, g, b]
    .map((channel) => channel.toString(16).padStart(2, '0'))
    .join('')}`.toUpperCase();
}
