import React, { useEffect } from 'react';
import { Animated, View, Text, TouchableOpacity, StyleSheet, Platform } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Colors, Spacing } from '../../theme';
import type { FriendlyError } from '../../services/error.service';

interface ToastProps {
  message: string;
  type: 'error' | 'warning' | 'success' | 'info';
  duration?: number;
  onDismiss?: () => void;
  isDismissible?: boolean;
  icon?: string;
}

const typeConfig = {
  error: {
    backgroundColor: '#FEE2E2',
    textColor: '#991B1B',
    borderColor: '#FCA5A5',
    icon: 'alert-circle',
  },
  warning: {
    backgroundColor: '#FEF3C7',
    textColor: '#92400E',
    borderColor: '#FCD34D',
    icon: 'alert',
  },
  success: {
    backgroundColor: '#DCFCE7',
    textColor: '#166534',
    borderColor: '#86EFAC',
    icon: 'checkmark-circle',
  },
  info: {
    backgroundColor: '#DBEAFE',
    textColor: '#0C4A6E',
    borderColor: '#7DD3FC',
    icon: 'information-circle',
  },
};

export function Toast({
  message,
  type,
  duration = 3000,
  onDismiss,
  isDismissible = true,
  icon,
}: ToastProps) {
  const config = typeConfig[type];
  const insets = useSafeAreaInsets();
  const fadeAnim = React.useRef(new Animated.Value(0)).current;

  useEffect(() => {
    // Fade in
    Animated.timing(fadeAnim, {
      toValue: 1,
      duration: 300,
      useNativeDriver: true,
    }).start();

    // Auto dismiss
    const timer = setTimeout(() => {
      Animated.timing(fadeAnim, {
        toValue: 0,
        duration: 300,
        useNativeDriver: true,
      }).start(() => {
        onDismiss?.();
      });
    }, duration);

    return () => clearTimeout(timer);
  }, [duration, fadeAnim, onDismiss]);

  return (
    <Animated.View
      style={[
        styles.container,
        {
          bottom: Math.max(insets.bottom, Platform.OS === 'android' ? 8 : 0) + (Platform.OS === 'ios' ? 72 : 64),
          opacity: fadeAnim,
          transform: [
            {
              translateY: fadeAnim.interpolate({
                inputRange: [0, 1],
                outputRange: [50, 0],
              }),
            },
          ],
        },
      ]}
    >
      <View
        style={[
          styles.toast,
          {
            backgroundColor: config.backgroundColor,
            borderColor: config.borderColor,
          },
        ]}
      >
        <Ionicons
          name={(icon || config.icon) as any}
          size={20}
          color={config.textColor}
          style={styles.icon}
        />
        <Text
          style={[
            styles.message,
            {
              color: config.textColor,
            },
          ]}
          numberOfLines={2}
        >
          {message}
        </Text>
        {isDismissible && (
          <TouchableOpacity
            onPress={onDismiss}
            hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
          >
            <Ionicons name="close" size={18} color={config.textColor} />
          </TouchableOpacity>
        )}
      </View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  container: {
    position: 'absolute',
    left: Spacing.base || 16,
    right: Spacing.base || 16,
    zIndex: 1000,
  },
  toast: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: Spacing.base || 16,
    paddingVertical: Spacing.sm || 12,
    borderRadius: 12,
    borderWidth: 1,
    gap: Spacing.sm || 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  icon: {
    flexShrink: 0,
  },
  message: {
    flex: 1,
    fontSize: 14,
    fontWeight: '500',
    lineHeight: 20,
  },
});
