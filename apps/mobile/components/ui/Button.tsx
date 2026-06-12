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

type Variant = 'primary' | 'secondary' | 'ghost' | 'accent';
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
        styles[variant],
        styles[size],
        fullWidth && styles.fullWidth,
        (disabled || loading) && styles.disabled,
        style,
      ]}
    >
      {loading ? (
        <ActivityIndicator
          size="small"
          color={variant === 'ghost' ? Colors.primary : Colors.white}
        />
      ) : (
        <Text style={[styles.label, styles[`label_${variant}`], styles[`label_${size}`], textStyle]}>
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
