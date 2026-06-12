import React from 'react';
import {
  TouchableOpacity,
  Text,
  ActivityIndicator,
  StyleSheet,
  ViewStyle,
  TextStyle,
} from 'react-native';
import * as Haptics from 'expo-haptics';
import { Colors, Spacing, BorderRadius } from '../../theme';
import { useThemeColors } from '../../store/theme.store';

type Variant = 'primary' | 'secondary' | 'ghost' | 'accent' | 'outline';
type Size = 'sm' | 'md' | 'lg';

interface ButtonProps {
  label: string;
  onPress: () => void;
  variant?: Variant;
  size?: Size;
  loading?: boolean;
  disabled?: boolean;
  fullWidth?: boolean;
  style?: ViewStyle;
  textStyle?: TextStyle;
  // Accessibility (WCAG AA compliance)
  accessibilityLabel?: string;
  accessibilityRole?: 'button' | 'link' | 'menuitem';
  accessibilityHint?: string;
  testID?: string;
}

export function Button({
  label,
  onPress,
  variant = 'primary',
  size = 'md',
  loading = false,
  disabled = false,
  fullWidth = false,
  style,
  textStyle,
  accessibilityLabel,
  accessibilityRole = 'button',
  accessibilityHint,
  testID,
}: ButtonProps) {
  const theme = useThemeColors();

  async function handlePress() {
    await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    onPress();
  }

  return (
    <TouchableOpacity
      onPress={handlePress}
      disabled={disabled || loading}
      activeOpacity={0.8}
      accessibilityLabel={accessibilityLabel || label}
      accessibilityRole={accessibilityRole}
      accessibilityHint={accessibilityHint}
      testID={testID}
      style={[
        styles.base,
        getVariantStyle(variant, theme),
        styles[size],
        fullWidth && styles.fullWidth,
        (disabled || loading) && styles.disabled,
        style,
      ]}
    >
      {loading ? (
        <ActivityIndicator
          size="small"
          color={variant === 'ghost' ? theme.primary : getLoadingColor(variant, theme)}
        />
      ) : (
        <Text style={[styles.label, getLabelStyle(variant, theme), styles[`label_${size}`], textStyle]}>
          {label}
        </Text>
      )}
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  base: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: BorderRadius.lg,
    borderWidth: 1.5,
    borderColor: 'transparent',
  },
  fullWidth: { width: '100%' },
  disabled: { opacity: 0.5 },

  // Variants
  primary:   { backgroundColor: Colors.primary },
  secondary: { backgroundColor: Colors.border },
  ghost:     { backgroundColor: 'transparent', borderWidth: 1.5, borderColor: Colors.primary },
  accent:    { backgroundColor: Colors.accent },

  // Sizes
  sm: { paddingVertical: Spacing.xs,   paddingHorizontal: Spacing.md,   minHeight: 36 },
  md: { paddingVertical: Spacing.sm,   paddingHorizontal: Spacing.xl,   minHeight: 48 },
  lg: { paddingVertical: Spacing.md,   paddingHorizontal: Spacing['2xl'], minHeight: 56 },

  // Labels
  label:           { fontWeight: '600', letterSpacing: 0.2 },
  label_primary:   { color: Colors.white },
  label_secondary: { color: Colors.text },
  label_ghost:     { color: Colors.primary },
  label_accent:    { color: Colors.white },
  label_sm:        { fontSize: 13 },
  label_md:        { fontSize: 15 },
  label_lg:        { fontSize: 17 },
});

function getVariantStyle(variant: Variant, theme: typeof Colors): ViewStyle {
  switch (variant) {
    case 'primary':
      return { backgroundColor: theme.button, borderColor: getButtonBorderColor(theme) };
    case 'accent':
      return { backgroundColor: theme.accent, borderColor: theme.accent };
    case 'outline':
      return { backgroundColor: theme.button, borderColor: getButtonBorderColor(theme) };
    case 'ghost':
      return { backgroundColor: 'transparent', borderWidth: 1.5, borderColor: theme.primary };
    case 'secondary':
    default:
      return { backgroundColor: theme.border };
  }
}

function getLabelStyle(variant: Variant, theme: typeof Colors): TextStyle {
  switch (variant) {
    case 'ghost':
      return { color: theme.primary };
    case 'outline':
      return { color: theme.buttonText };
    case 'secondary':
      return { color: theme.text };
    case 'primary':
    case 'accent':
    default:
      return { color: variant === 'primary' ? theme.buttonText : theme.white };
  }
}

function getLoadingColor(variant: Variant, theme: typeof Colors): string {
  if (variant === 'primary') return theme.buttonText;
  if (variant === 'outline') return theme.buttonText;
  if (variant === 'ghost') return theme.button;
  return theme.white;
}

function getButtonBorderColor(theme: typeof Colors): string {
  return isLightColor(theme.button) ? theme.buttonText : theme.button;
}

function isLightColor(hex: string): boolean {
  if (!/^#[0-9A-Fa-f]{6}$/.test(hex)) return false;

  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;

  return luminance > 0.72;
}
