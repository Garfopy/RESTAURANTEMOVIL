import React, { useState } from 'react';
import { View, TextInput, Text, TouchableOpacity, StyleSheet, Platform, ViewStyle, TextStyle } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Colors, Spacing } from '../../theme';
import { useThemeColors } from '../../store/theme.store';

interface FormFieldProps {
  label: string;
  value: string;
  onChangeText: (text: string) => void;
  placeholder?: string;
  error?: string | null;
  onBlur?: () => void;
  onFocus?: () => void;
  keyboardType?: 'default' | 'email-address' | 'numeric' | 'phone-pad' | 'decimal-pad';
  secureTextEntry?: boolean;
  autoCapitalize?: 'none' | 'sentences' | 'words' | 'characters';
  autoComplete?: 'off' | 'email' | 'password' | 'username' | 'name';
  editable?: boolean;
  onToggleSecure?: () => void;
  icon?: string;
  testID?: string;
  accessibilityLabel?: string;
  accessibilityHint?: string;
  containerStyle?: ViewStyle;
  labelStyle?: TextStyle;
  inputWrapperStyle?: ViewStyle;
  inputStyle?: TextStyle;
  placeholderTextColor?: string;
  iconColor?: string;
  errorIconColor?: string;
  focusedBorderColor?: string;
  focusedBackgroundColor?: string;
  errorInputWrapperStyle?: ViewStyle;
  errorTextStyle?: TextStyle;
}

export function FormField({
  label,
  value,
  onChangeText,
  placeholder,
  error,
  onBlur,
  onFocus,
  keyboardType = 'default',
  secureTextEntry = false,
  autoCapitalize = 'none',
  autoComplete = 'off',
  editable = true,
  onToggleSecure,
  icon,
  testID,
  accessibilityLabel,
  accessibilityHint,
  containerStyle,
  labelStyle,
  inputWrapperStyle,
  inputStyle,
  placeholderTextColor,
  iconColor,
  errorIconColor,
  focusedBorderColor,
  focusedBackgroundColor,
  errorInputWrapperStyle,
  errorTextStyle,
}: FormFieldProps) {
  const [isFocused, setIsFocused] = useState(false);
  const theme = useThemeColors();

  const hasError = !!error;

  return (
    <View style={[styles.container, containerStyle]}>
      {label ? <Text style={[styles.label, labelStyle]}>{label}</Text> : null}
      <View
        style={[
          styles.inputWrapper,
          inputWrapperStyle,
          isFocused && {
            borderColor: focusedBorderColor ?? theme.primary,
            backgroundColor: focusedBackgroundColor ?? '#FAFAFA',
          },
          hasError && styles.inputWrapperError,
          hasError && errorInputWrapperStyle,
        ]}
      >
        {icon && (
          <Ionicons
            name={icon as any}
            size={20}
            color={hasError ? errorIconColor ?? Colors.error ?? '#DC2626' : isFocused ? focusedBorderColor ?? theme.primary : iconColor ?? Colors.textMuted}
            style={styles.icon}
          />
        )}
        <TextInput
          style={[styles.input, inputStyle]}
          value={value}
          onChangeText={onChangeText}
          placeholder={placeholder}
          placeholderTextColor={placeholderTextColor ?? Colors.textMuted ?? '#9CA3AF'}
          keyboardType={keyboardType}
          secureTextEntry={secureTextEntry}
          autoCapitalize={autoCapitalize}
          autoComplete={autoComplete}
          editable={editable}
          onFocus={() => {
            setIsFocused(true);
            onFocus?.();
          }}
          onBlur={() => {
            setIsFocused(false);
            onBlur?.();
          }}
          testID={testID}
          accessibilityLabel={accessibilityLabel || label}
          accessibilityHint={accessibilityHint}
        />
        {onToggleSecure && (
          <TouchableOpacity
            onPress={onToggleSecure}
            hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
            accessibilityLabel={secureTextEntry ? 'Mostrar contraseña' : 'Ocultar contraseña'}
            accessibilityRole="button"
            testID="toggle-password"
          >
            <Ionicons
              name={secureTextEntry ? 'eye-off-outline' : 'eye-outline'}
              size={20}
              color={iconColor ?? Colors.textMuted}
            />
          </TouchableOpacity>
        )}
      </View>
      {hasError && (
        <View style={styles.errorContainer}>
          <Ionicons
            name="alert-circle"
            size={14}
            color={errorIconColor ?? Colors.error ?? '#DC2626'}
            style={styles.errorIcon}
          />
          <Text style={[styles.errorText, errorTextStyle]}>{error}</Text>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    gap: 8,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#111827',
    marginLeft: 4,
  },
  inputWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1.5,
    borderColor: '#E5E7EB',
    borderRadius: 12,
    paddingHorizontal: Spacing.base || 16,
    paddingVertical: Platform.OS === 'ios' ? Spacing.sm || 12 : Spacing.xs || 8,
    backgroundColor: '#FFFFFF',
    gap: Spacing.sm || 12,
  },
  inputWrapperFocused: {
    borderColor: Colors.primary || '#111827',
    backgroundColor: '#FAFAFA',
  },
  inputWrapperError: {
    borderColor: Colors.error || '#DC2626',
    backgroundColor: '#FEF2F2',
  },
  icon: {
    flexShrink: 0,
  },
  input: {
    flex: 1,
    fontSize: 15,
    color: '#111827',
    padding: 0,
  },
  errorContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginLeft: 4,
  },
  errorIcon: {
    flexShrink: 0,
  },
  errorText: {
    fontSize: 13,
    color: Colors.error || '#DC2626',
    fontWeight: '500',
  },
});
