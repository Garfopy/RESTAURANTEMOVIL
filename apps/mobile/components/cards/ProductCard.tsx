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
import { useRouter } from 'expo-router';
import { Colors, Spacing, Shadows, Typography } from '../../theme';
import { formatImageUrl } from '../../services/api';
import type { Platillo } from '@amare/types';
import { useThemeColors } from '../../store/theme.store';
import { requireAuth } from '../../services/auth-gate.service';
import { useCartStore } from '../../store/cart.store';
import { useToast } from '../../context/ToastContext';

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
  const router = useRouter();
  const scale = useRef(new Animated.Value(1)).current;
  const theme = useThemeColors();
  const addItem = useCartStore((state) => state.addItem);
  const toast = useToast();

  // ✅ React Query source of truth
  const { data: favorites = [], toggle } = useFavorites();

  const isFavorite = favorites.some((p) => p.id === platillo.id);
  const requiresCustomization = Boolean(
    platillo.selector ||
      platillo.modificadores?.some((mod) => mod.requerido || Number(mod.min_selecciones ?? 0) > 0)
  );

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

  function handleQuickAdd() {
    if (platillo.disponible === false) return;

    if (requiresCustomization) {
      onPress(platillo);
      return;
    }

    addItem(platillo, 1, [], '');
    toast.success('Agregado al carrito');
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
          onPress={() => {
            if (!requireAuth(router, {
              message: 'Crea tu cuenta para guardar favoritos, recibir ofertas y encontrarlos rapido despues.',
              returnTo: '/(tabs)/favorites',
            })) return;
            void toggle(platillo.id);
          }}
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

        <TouchableOpacity
          style={[styles.quickAddBadge, platillo.disponible === false && styles.quickAddBadgeDisabled]}
          onPress={(event) => {
            event.stopPropagation();
            handleQuickAdd();
          }}
          disabled={platillo.disponible === false}
          accessibilityLabel={requiresCustomization ? 'Personalizar platillo' : 'Agregar al carrito'}
          accessibilityRole="button"
          testID={`quick-add-btn-${platillo.id}`}
        >
          <Ionicons
            name={requiresCustomization ? 'options-outline' : 'add'}
            size={16}
            color={platillo.disponible === false ? Colors.textMuted : Colors.white}
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
  quickAddBadge: {
    position: 'absolute',
    right: 8,
    bottom: 8,
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: Colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    ...Shadows.sm,
  },
  quickAddBadgeDisabled: {
    backgroundColor: '#E5E7EB',
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
