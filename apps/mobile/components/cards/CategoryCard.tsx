import React from 'react';
import { TouchableOpacity, Text, StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { Colors, Spacing, Typography } from '../../theme';
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
      activeOpacity={0.85}
      style={[styles.card, { width, height }]}
    >
      {categoria.imagen ? (
        <Image
          source={categoria.imagen}
          style={StyleSheet.absoluteFill}
          contentFit="cover"
          transition={200}
        />
      ) : (
        <View style={[StyleSheet.absoluteFill, styles.placeholder]} />
      )}
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
