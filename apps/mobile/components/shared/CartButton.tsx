import React, { useRef } from 'react';
import { Animated, View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useCartStore } from '../../store/cart.store';
import { Colors, Shadows } from '../../theme';
import { useRouter } from 'expo-router';

export function CartButton() {
  const router = useRouter();
  const itemCount = useCartStore((s) => s.itemCount);
  const total = useCartStore((s) => s.total);
  const scale = useRef(new Animated.Value(1)).current;

  const animStyle = { transform: [{ scale }] };

  if (itemCount === 0) return null;

  function handlePress() {
    Animated.sequence([
      Animated.spring(scale, { toValue: 0.94, damping: 12, useNativeDriver: true } as any),
      Animated.spring(scale, { toValue: 1, damping: 12, useNativeDriver: true } as any),
    ]).start();
    router.push('/cart');
  }

  return (
    <Animated.View style={[styles.container, animStyle]}>
      <TouchableOpacity style={styles.button} onPress={handlePress} activeOpacity={0.9}>
        <View style={styles.badge}>
          <Text style={styles.badgeText}>{itemCount}</Text>
        </View>
        <Ionicons name="bag" size={20} color={Colors.white} />
        <Text style={styles.label}>Ver pedido</Text>
        <Text style={styles.price}>${total.toFixed(2)}</Text>
      </TouchableOpacity>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  container: {
    position: 'absolute',
    bottom: 100,
    left: 20,
    right: 20,
    zIndex: 100,
    ...Shadows.lg,
  },
  button: {
    backgroundColor: Colors.primary,
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 14,
    paddingHorizontal: 20,
    borderRadius: 16,
    gap: 10,
  },
  badge: {
    backgroundColor: Colors.accent,
    borderRadius: 12,
    minWidth: 24,
    height: 24,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 6,
  },
  badgeText: {
    color: Colors.white,
    fontSize: 12,
    fontWeight: '700',
  },
  label: {
    flex: 1,
    color: Colors.white,
    fontSize: 15,
    fontWeight: '600',
  },
  price: {
    color: Colors.accentLight,
    fontSize: 15,
    fontWeight: '700',
  },
});
