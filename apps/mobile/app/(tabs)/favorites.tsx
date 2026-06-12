import React, { useEffect, useCallback } from 'react';
import { 
  View, 
  Text, 
  StyleSheet, 
  FlatList, 
  SafeAreaView, 
  Dimensions 
} from 'react-native';
import { useRouter, useFocusEffect } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { Ionicons } from '@expo/vector-icons';

import { apiClient } from '../../services/api';
import { useFavoritesStore } from '../../store/favorites.store';
import { useBranchStore } from '../../store/branch.store';
import { ProductCard } from '../../components/cards/ProductCard';
import { EmptyState } from '../../components/ui/EmptyState';
import { Skeleton } from '../../components/ui/Skeleton';
import { Colors, Spacing, Typography } from '../../theme';
import type { Platillo } from '@amare/types';

// Calculamos el ancho de la tarjeta para que respire perfectamente en la cuadrícula
const { width } = Dimensions.get('window');
const CARD_MARGIN = 16;
const PADDING_HORIZONTAL = 24;
const CARD_WIDTH = (width - PADDING_HORIZONTAL * 2 - CARD_MARGIN) / 2;

export default function FavoritesScreen() {
  const router = useRouter();
  const syncFromServer = useFavoritesStore((s) => s.syncFromServer);
  const restauranteId = useBranchStore((s) => s.seleccionada?.id);

  const { data: favorites, isLoading, refetch } = useQuery<Platillo[]>({
    queryKey: ['favorites'],
    queryFn: async () => {
      const res = await apiClient.get('/favorites');
      return res.data.data;
    },
  });

  useFocusEffect(
    useCallback(() => {
      refetch();
    }, [refetch])
  );

  useEffect(() => {
    if (favorites) {
      syncFromServer(favorites.map((p) => p.id));
    }
  }, [favorites, syncFromServer]);

  function handleDish(p: Platillo) {
    if (!restauranteId) return;
    router.push({ 
      pathname: '/product/[id]', 
      params: { id: String(p.id), restauranteId: String(restauranteId) } 
    });
  }

  // Renderizado del Header Premium
  const renderHeader = () => (
    <View style={styles.header}>
      <View style={styles.headerTitleRow}>
        <Text style={styles.title}>Mis Favoritos</Text>
        <Ionicons name="heart" size={32} color={Colors.primary || '#EF4444'} />
      </View>
      <Text style={styles.subtitle}>
        Tus platillos guardados para pedir rápidamente.
      </Text>
    </View>
  );

  // Estado de carga con diseño de cuadrícula (Skeleton)
  if (isLoading) {
    return (
      <SafeAreaView style={styles.safe}>
        {renderHeader()}
        <View style={styles.skeletonGrid}>
          {[1, 2, 3, 4, 5, 6].map((k) => (
            <View key={k} style={{ width: CARD_WIDTH, marginBottom: 24 }}>
              <Skeleton height={CARD_WIDTH} borderRadius={20} />
              <View style={styles.skeletonTextWrap}>
                <Skeleton height={16} borderRadius={6} width="85%" />
                <Skeleton height={14} borderRadius={6} width="50%" />
              </View>
            </View>
          ))}
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safe}>
      {renderHeader()}
      
      {favorites && favorites.length > 0 ? (
        <FlatList
          data={favorites}
          keyExtractor={(p) => String(p.id)}
          numColumns={2}
          contentContainerStyle={styles.list}
          columnWrapperStyle={styles.row}
          showsVerticalScrollIndicator={false}
          renderItem={({ item }) => (
            <View style={{ width: CARD_WIDTH }}>
              <ProductCard 
                platillo={item} 
                onPress={handleDish} 
                width={CARD_WIDTH} 
              />
            </View>
          )}
        />
      ) : (
        <View style={styles.emptyContainer}>
          <EmptyState
            icon="heart-outline"
            title="Aún no tienes favoritos"
            description="Explora nuestro menú y toca el corazón en los platillos que más te gusten para guardarlos aquí."
          />
        </View>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { 
    flex: 1, 
    backgroundColor: Colors.background || '#F9FAFB' 
  },
  header: {
    paddingHorizontal: PADDING_HORIZONTAL,
    paddingTop: Spacing.xl || 32,
    paddingBottom: Spacing.lg || 24,
  },
  headerTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  title: { 
    ...Typography.h1, 
    fontSize: 34,
    fontWeight: '800', 
    color: Colors.text || '#111827',
    letterSpacing: -0.5,
    lineHeight: 42,      // Le da suficiente altura a la línea para que entren los puntos y acentos
    paddingTop: 4,       // Empuja el texto ligeramente hacia abajo dentro de su propia caja
  },
  subtitle: {
    ...Typography.body,
    fontSize: 16,
    color: '#6B7280',
    lineHeight: 22,
  },
  list: { 
    paddingHorizontal: PADDING_HORIZONTAL, 
    paddingBottom: 120, 
    paddingTop: Spacing.sm 
  },
  row: { 
    justifyContent: 'space-between', 
    marginBottom: CARD_MARGIN * 1.5,
  },
  skeletonGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    paddingHorizontal: PADDING_HORIZONTAL,
    paddingTop: 16,
  },
  skeletonTextWrap: {
    marginTop: 12,
    gap: 8,
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 32,
    paddingBottom: 64, // Para compensar visualmente el tab bar
  }
});