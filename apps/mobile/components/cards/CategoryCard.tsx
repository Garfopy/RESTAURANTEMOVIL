import React from 'react';
import { TouchableOpacity, Text, StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { Colors, Spacing, Typography } from '../../theme';
import { formatImageUrl } from '../../services/api';
// Nota: Asegúrate de que el tipo `Categoria` en `@amare/types`
// tenga una propiedad `imagen?: string;` para que esto funcione correctamente.
// El backend debe proveer la URL de la imagen en este campo.
import type { Categoria } from '@amare/types';

interface CategoryCardProps {
  categoria: Categoria;
  onPress: (categoria: Categoria) => void;
  width?: number;
  height?: number;
}

export function CategoryCard({ categoria, onPress, width = 140, height = 100 }: CategoryCardProps) {
  return (
    <TouchableOpacity
      onPress={() => onPress(categoria)}
      delayPressIn={100}
      activeOpacity={0.85}
      style={[styles.card, { width, height }]}
      accessibilityLabel={`${categoria.nombre}`}
      accessibilityRole="button"
      accessibilityHint={`${categoria.total_platillos ?? 0} platillos disponibles`}
      testID={`category-card-${categoria.id}`}
    >
      <Image
        source={formatImageUrl(categoria.imagen) || require('../../assets/placeholder-food.jpg')}
        style={StyleSheet.absoluteFill}
        contentFit="cover"
        transition={300}
        placeholder={{ blurhash: 'L38Wx:0000_300~q_3Rj00-;%M_3' }}
      />
      <LinearGradient
        colors={['transparent', 'rgba(26,26,46,0.85)']}
        style={StyleSheet.absoluteFill}
      />
      <View style={styles.labelContainer}>
        <Text style={styles.nombre} numberOfLines={2}>{categoria.nombre}</Text>
        {(categoria.total_platillos ?? 0) > 0 && (
          <Text style={styles.count}>{categoria.total_platillos} platillos</Text>
        )}
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 14,
    overflow: 'hidden',
    marginRight: Spacing.sm,
    justifyContent: 'flex-end',
  },
  placeholder: {
    backgroundColor: Colors.primaryLight,
  },
  labelContainer: {
    padding: Spacing.sm,
  },
  nombre: {
    ...Typography.bodySM,
    color: Colors.white,
    fontWeight: '700',
    lineHeight: 16,
  },
  count: {
    fontSize: 10,
    color: 'rgba(255,255,255,0.7)',
    marginTop: 2,
  },
});
