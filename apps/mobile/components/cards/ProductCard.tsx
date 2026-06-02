import React from 'react';
import {
  TouchableOpacity,
  Text,
  StyleSheet,
  View,
  Platform,
} from 'react-native';
import { Image } from 'expo-image';
import Animated, {
  useAnimatedStyle,
  useSharedValue,
  withSpring,
} from 'react-native-reanimated';
import { Ionicons } from '@expo/vector-icons';
import { Colors, Spacing, Shadows, Typography } from '../../theme';
import { useFavoritesStore } from '../../store/favorites.store';
import type { Platillo } from '@amare/types';

const AnimatedTouchable = Animated.createAnimatedComponent(TouchableOpacity);

interface ProductCardProps {
  platillo: Platillo;
  onPress: (platillo: Platillo) => void;
  width?: number;
}

export function ProductCard({ platillo, onPress, width = 180 }: ProductCardProps) {
  const scale = useSharedValue(1);
  const isFavorite = useFavoritesStore((s) => s.isFavorite(platillo.id));

  const animStyle = useAnimatedStyle(() => ({
    transform: [{ scale: scale.value }],
  }));

  function handlePressIn() {
    scale.value = withSpring(0.96, { damping: 15 });
  }

  function handlePressOut() {
    scale.value = withSpring(1, { damping: 12 });
    onPress(platillo);
  }

  return (
    <AnimatedTouchable
      onPressIn={handlePressIn}
      onPressOut={handlePressOut}
      activeOpacity={1}
      style={[styles.card, { width }, animStyle]}
    >
      <View style={styles.imageContainer}>
        <Image
          source={platillo.imagen ?? require('../../assets/placeholder-food.png')}
          style={styles.image}
          contentFit="cover"
          transition={300}
        />
        {!platillo.disponible && (
          <View style={styles.unavailableOverlay}>
            <Text style={styles.unavailableText}>No disponible</Text>
          </View>
        )}
        {isFavorite && (
          <View style={styles.favBadge}>
            <Ionicons name="heart" size={12} color={Colors.error} />
          </View>
        )}
      </View>
      <View style={styles.info}>
        <Text style={styles.nombre} numberOfLines={2}>
          {platillo.nombre}
        </Text>
        {platillo.tiempo_preparacion_min > 0 && (
          <View style={styles.time}>
            <Ionicons name="time-outline" size={12} color={Colors.textMuted} />
            <Text style={styles.timeText}>{platillo.tiempo_preparacion_min} min</Text>
          </View>
        )}
        <Text style={styles.precio}>${platillo.precio.toFixed(2)}</Text>
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
    marginRight: Spacing.sm,
  },
  imageContainer: { width: '100%', height: 130, position: 'relative' },
  image: { width: '100%', height: '100%' },
  unavailableOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0,0,0,0.45)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  unavailableText: { color: Colors.white, fontSize: 12, fontWeight: '600' },
  favBadge: {
    position: 'absolute',
    top: 8,
    right: 8,
    backgroundColor: Colors.surface,
    borderRadius: 12,
    padding: 4,
  },
  info: { padding: Spacing.sm },
  nombre: {
    ...Typography.bodySM,
    color: Colors.text,
    fontWeight: '600',
    lineHeight: 18,
  },
  time: { flexDirection: 'row', alignItems: 'center', gap: 3, marginTop: 4 },
  timeText: { fontSize: 11, color: Colors.textMuted },
  precio: {
    ...Typography.price,
    color: Colors.primary,
    fontWeight: '700',
    marginTop: 4,
  },
});
