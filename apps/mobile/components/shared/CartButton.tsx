import React, { useRef } from 'react';
import { Animated, View, Text, TouchableOpacity, StyleSheet, Platform } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useCartStore } from '../../store/cart.store';
import { Colors } from '../../theme'; // Ajusta según tu archivo de colores
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
      Animated.spring(scale, { toValue: 0.96, damping: 12, useNativeDriver: true }),
      Animated.spring(scale, { toValue: 1, damping: 12, useNativeDriver: true }),
    ]).start();
    router.push('/cart');
  }

  return (
    <Animated.View style={[styles.container, animStyle]}>
      <TouchableOpacity style={styles.button} onPress={handlePress} activeOpacity={0.8}>
        <View style={styles.badge}>
          <Text style={styles.badgeText}>{itemCount}</Text>
        </View>
        <Text style={styles.label}>Ver pedido</Text>
        <Text style={styles.price}>${total.toFixed(2)}</Text>
        <Ionicons name="chevron-forward" size={18} color="#000" style={{ marginLeft: 5 }} />
      </TouchableOpacity>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  container: {
    position: 'absolute',
    bottom: Platform.OS === 'ios' ? 125 : 110,
    left: 20,
    right: 20,
    zIndex: 100,
  },
  button: {
    // Estilo Moderno: Fondo blanco traslúcido
    backgroundColor: 'rgba(255, 255, 255, 0.95)', 
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 16,
    paddingHorizontal: 20,
    borderRadius: 20, // Más redondeado para un look moderno
    borderWidth: 1,
    borderColor: 'rgba(0,0,0,0.05)', // Borde muy fino
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 10,
    elevation: 5,
    gap: 12,
  },
  badge: {
    backgroundColor: '#000', // Badge oscuro para contraste
    borderRadius: 10,
    minWidth: 24,
    height: 24,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 6,
  },
  badgeText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '800',
  },
  label: {
    flex: 1,
    color: '#1a1a1a',
    fontSize: 16,
    fontWeight: '600',
  },
  price: {
    color: '#1a1a1a',
    fontSize: 16,
    fontWeight: '700',
  },
});