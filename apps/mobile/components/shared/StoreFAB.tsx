import React, { useRef } from 'react';
import { Animated, TouchableOpacity, StyleSheet, Platform, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { Colors } from '../../theme';
import { useThemeColors } from '../../store/theme.store';

export function StoreFAB() {
  const router = useRouter();
  const theme = useThemeColors();
  const scale = useRef(new Animated.Value(1)).current;

  function handlePress() {
    Animated.sequence([
      Animated.spring(scale, { toValue: 0.85, damping: 12, useNativeDriver: true }),
      Animated.spring(scale, { toValue: 1, damping: 12, useNativeDriver: true }),
    ]).start();
    router.push('/store');
  }

  return (
    <Animated.View style={[styles.container, { transform: [{ scale }] }]}>
      <TouchableOpacity
        style={[styles.button, { backgroundColor: theme.primary }]}
        onPress={handlePress}
        activeOpacity={0.85}
        accessibilityLabel="Ir a tienda"
        accessibilityRole="button"
        accessibilityHint="Navega a la tienda principal"
        testID="store-fab"
      >
        <Ionicons name="storefront-outline" size={24} color="#FFF" />
        <Text style={styles.label}>Tienda</Text>
      </TouchableOpacity>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  container: {
    position: 'absolute',
    bottom: Platform.OS === 'ios' ? 200 : 185, // Above CartButton (~125 iOS / ~110 Android)
    right: 20,
    zIndex: 150,
  },
  button: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: Colors.primary,
    paddingVertical: 12,
    paddingHorizontal: 18,
    borderRadius: 28,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.2,
    shadowRadius: 8,
    elevation: 6,
  },
  label: {
    color: '#FFF',
    fontSize: 13,
    fontWeight: '700',
  },
});
