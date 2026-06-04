import React, { useEffect, useCallback } from 'react';
import { View, Text, StyleSheet, FlatList, SafeAreaView } from 'react-native';
import { useRouter, useFocusEffect } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '../../services/api';
import { useFavoritesStore } from '../../store/favorites.store';
import { ProductCard } from '../../components/cards/ProductCard';
import { EmptyState } from '../../components/ui/EmptyState';
import { Skeleton } from '../../components/ui/Skeleton';
import { Colors, Spacing, Typography } from '../../theme';
import type { Platillo } from '@amare/types';
import { useBranchStore } from '../../store/branch.store';

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

  // Forzar actualización cada vez que el usuario entra a la pestaña
  useFocusEffect(
    useCallback(() => {
      refetch();
    }, [refetch])
  );

  // Sincronizar el store global cuando cambian los datos (reemplazo de onSuccess)
  useEffect(() => {
    if (favorites) {
      syncFromServer(favorites.map((p) => p.id));
    }
  }, [favorites, syncFromServer]);

  function handleDish(p: Platillo) {
    if (!restauranteId) return;
    router.push({ pathname: '/product/[id]', params: { id: String(p.id), restauranteId: String(restauranteId) } });
  }

  if (isLoading) {
    return (
      <SafeAreaView style={styles.safe}>
        <View style={styles.header}><Text style={styles.title}>Favoritos</Text></View>
        <View style={{ padding: Spacing.base, gap: 12 }}>
          {[1, 2, 3].map((k) => <Skeleton key={k} height={80} borderRadius={12} />)}
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <Text style={styles.title}>Favoritos</Text>
      </View>
      {favorites && favorites.length > 0 ? (
        <FlatList
          data={favorites}
          keyExtractor={(p) => String(p.id)}
          numColumns={2}
          contentContainerStyle={styles.list}
          columnWrapperStyle={styles.row}
          renderItem={({ item }) => (
            <ProductCard platillo={item} onPress={handleDish} width={160} />
          )}
        />
      ) : (
        <EmptyState
          icon="heart-outline"
          title="Sin favoritos"
          description="Marca platillos como favoritos para verlos aquí."
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  header: {
    paddingHorizontal: Spacing.base,
    paddingVertical: Spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  title: { ...Typography.h2, fontWeight: '700', color: Colors.text },
  list: { paddingHorizontal: Spacing.base, paddingBottom: 100, paddingTop: Spacing.sm },
  row: { justifyContent: 'space-between', marginBottom: Spacing.sm },
});
