import React, { useRef } from 'react';
import {
  Animated,
  TouchableOpacity,
  Text,
  StyleSheet,
  View,
} from 'react-native';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import { Colors, Spacing, Shadows, Typography } from '../../theme';
import { formatImageUrl } from '../../services/api';
import type { Platillo } from '@amare/types';
import { useThemeColors } from '../../store/theme.store';

import { useFavorites } from '../../hooks/useFavorites';

const AnimatedTouchable = Animated.createAnimatedComponent(TouchableOpacity);

interface ProductCardProps {
  platillo: Platillo;
  onPress: (platillo: Platillo) => void;
  width?: number;
}

export function ProductCard({
  platillo,
  onPress,
  width = 180,
}: ProductCardProps) {
  const scale = useRef(new Animated.Value(1)).current;
  const theme = useThemeColors();

  // ✅ React Query source of truth
  const { data: favorites = [], toggle } = useFavorites();

  const isFavorite = favorites.some((p) => p.id === platillo.id);

  const animStyle = { transform: [{ scale }] };

  function handlePressIn() {
    Animated.spring(scale, {
      toValue: 0.96,
      damping: 15,
      useNativeDriver: true,
    }).start();
  }

  function handlePressOut() {
    Animated.spring(scale, {
      toValue: 1,
      damping: 12,
      useNativeDriver: true,
    }).start();
  }

  return (
    <AnimatedTouchable
      onPress={() => onPress(platillo)}
      onPressIn={handlePressIn}
      onPressOut={handlePressOut}
      delayPressIn={100}
      activeOpacity={1}
      style={[styles.card, { width }, animStyle]}
      accessibilityLabel={`${platillo.nombre}, $${platillo.precio.toFixed(2)}`}
      accessibilityRole="button"
      accessibilityHint={platillo.disponible === false ? "No disponible" : "Ver detalles del producto"}
      testID={`product-card-${platillo.id}`}
    >
      {/* IMAGE */}
      <View style={styles.imageContainer}>
        <Image
          source={
            formatImageUrl(platillo.imagen) ??
            require('../../assets/placeholder-food.jpg')
          }
          style={styles.image}
          contentFit="cover"
          transition={300}
        />

        {platillo.disponible === false && (
          <View style={styles.unavailableOverlay}>
            <Text style={styles.unavailableText}>No disponible</Text>
          </View>
        )}

        {/* ❤️ FAVORITO TOGGLE */}
        <TouchableOpacity
          style={[
            styles.favBadge,
            isFavorite && { backgroundColor: '#FFE4E6' },
          ]}
          onPress={() => toggle(platillo.id)}
          accessibilityLabel={isFavorite ? "Eliminar de favoritos" : "Agregar a favoritos"}
          accessibilityRole="button"
          testID={`favorite-btn-${platillo.id}`}
        >
          <Ionicons
            name={isFavorite ? 'heart' : 'heart-outline'}
            size={14}
            color={Colors.error}
          />
        </TouchableOpacity>
      </View>

      {/* INFO */}
      <View style={styles.info}>
        <Text style={styles.nombre} numberOfLines={2}>
          {platillo.nombre}
        </Text>

        {platillo.tiempo_preparacion_min > 0 && (
          <View style={styles.time}>
            <Ionicons
              name="time-outline"
              size={12}
              color={Colors.textMuted}
            />
            <Text style={styles.timeText}>
              {platillo.tiempo_preparacion_min} min
            </Text>
          </View>
        )}

        <Text style={[styles.precio, { color: theme.primary }]}>
          ${platillo.precio.toFixed(2)}
        </Text>
      </View>
    </AnimatedTouchable>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: Colors.surface,
    borderRadius: 14,
    overflow: 'hidden',
    ...Shadows.card,
  },
  imageContainer: {
    width: '100%',
    height: 130,
    position: 'relative',
  },
  image: {
    width: '100%',
    height: '100%',
  },
  unavailableOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0,0,0,0.45)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  unavailableText: {
    color: Colors.white,
    fontSize: 12,
    fontWeight: '600',
  },
  favBadge: {
    position: 'absolute',
    top: 8,
    right: 8,
    backgroundColor: Colors.surface,
    borderRadius: 12,
    padding: 6,
  },
  info: {
    padding: Spacing.sm,
  },
  nombre: {
    ...Typography.bodySM,
    color: Colors.text,
    fontWeight: '600',
    lineHeight: 18,
  },
  time: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    marginTop: 4,
  },
  timeText: {
    fontSize: 11,
    color: Colors.textMuted,
  },
  precio: {
    ...Typography.price,
    color: Colors.primary,
    fontWeight: '700',
    marginTop: 4,
  },
});
