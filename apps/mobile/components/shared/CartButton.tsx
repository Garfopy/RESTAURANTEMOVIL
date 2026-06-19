import React, { useRef } from 'react';
import {
  Animated,
  Platform,
  StyleSheet,
  Text,
  TouchableOpacity,
  useWindowDimensions,
  View,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useRouter, useSegments } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useCartStore } from '../../store/cart.store';

export function CartButton() {
  const router = useRouter();
  const [rootSegment] = useSegments();
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const itemCount = useCartStore((s) => s.itemCount);
  const total = useCartStore((s) => s.total);
  const scale = useRef(new Animated.Value(1)).current;
  const compact = width < 380;
  const safeBottom = Math.max(insets.bottom, Platform.OS === 'android' ? 8 : 0);
  const bottomOffset = safeBottom + (Platform.OS === 'ios' ? 92 : 82);

  if (itemCount === 0) return null;

  function handlePress() {
    Animated.sequence([
      Animated.spring(scale, { toValue: 0.96, damping: 12, useNativeDriver: true }),
      Animated.spring(scale, { toValue: 1, damping: 12, useNativeDriver: true }),
    ]).start();
    if (rootSegment === 'product' || rootSegment === 'store') {
      router.replace('/cart');
      return;
    }
    router.push('/cart');
  }

  return (
    <Animated.View
      style={[
        styles.container,
        {
          left: compact ? 12 : 20,
          right: compact ? 12 : 20,
          bottom: bottomOffset,
          transform: [{ scale }],
        },
      ]}
    >
      <TouchableOpacity
        style={[styles.button, compact && styles.buttonCompact]}
        onPress={handlePress}
        activeOpacity={0.8}
        accessibilityLabel={`Ver pedido: ${itemCount} articulos, $${total.toFixed(2)}`}
        accessibilityRole="button"
        accessibilityHint="Navega a tu carrito de compras"
        testID="cart-button"
      >
        <View style={styles.badge}>
          <Text style={styles.badgeText}>{itemCount}</Text>
        </View>
        <Text style={styles.label} numberOfLines={1}>Ver pedido</Text>
        <Text style={styles.price}>${total.toFixed(2)}</Text>
        <Ionicons name="chevron-forward" size={18} color="#111827" />
      </TouchableOpacity>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  container: {
    position: 'absolute',
    zIndex: 100,
    elevation: 20,
  },
  button: {
    backgroundColor: '#FFFFFF',
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 16,
    paddingHorizontal: 20,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: 'rgba(15, 23, 42, 0.08)',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 10,
    elevation: 8,
    gap: 12,
  },
  buttonCompact: {
    paddingVertical: 14,
    paddingHorizontal: 14,
    borderRadius: 18,
    gap: 8,
  },
  badge: {
    backgroundColor: '#111827',
    borderRadius: 10,
    minWidth: 24,
    height: 24,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 6,
  },
  badgeText: {
    color: '#FFFFFF',
    fontSize: 12,
    fontWeight: '800',
  },
  label: {
    flex: 1,
    color: '#111827',
    fontSize: 16,
    fontWeight: '700',
  },
  price: {
    color: '#111827',
    fontSize: 16,
    fontWeight: '800',
  },
});
