import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  SafeAreaView,
  TouchableOpacity,
} from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useDishes } from '../../hooks/useMenu';
import { ProductCard } from '../../components/cards/ProductCard';
import { SearchBar } from '../../components/ui/SearchBar';
import { CartButton } from '../../components/shared/CartButton';
import { SkeletonCard } from '../../components/ui/Skeleton';
import { EmptyState } from '../../components/ui/EmptyState';
import { Colors, Spacing, Typography } from '../../theme';
import type { Platillo } from '@amare/types';

export default function CategoryScreen() {
  const router = useRouter();
  const { id, restauranteId, q: initialQ } = useLocalSearchParams<{
    id: string;
    restauranteId: string;
    q?: string;
  }>();

  const [query, setQuery] = useState(initialQ ?? '');

  const categoriaId = id === 'search' ? undefined : Number(id);
  const restId = Number(restauranteId);

  const { data: dishes, isLoading } = useDishes(restId, {
    categoria_id: categoriaId,
    q: query || undefined,
  });

  function handleDish(p: Platillo) {
    router.push({ pathname: '/product/[id]', params: { id: String(p.id), restauranteId } });
  }

  return (
    <SafeAreaView style={styles.safe}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity onPress={() => router.back()}>
          <Ionicons name="arrow-back" size={24} color={Colors.text} />
        </TouchableOpacity>
        <Text style={styles.title} numberOfLines={1}>
          {id === 'search' ? 'Buscar' : 'Categoría'}
        </Text>
        <View style={{ width: 24 }} />
      </View>

      <View style={styles.searchBar}>
        <SearchBar value={query} onChangeText={setQuery} />
      </View>

      {isLoading ? (
        <FlatList
          data={[1, 2, 3, 4]}
          keyExtractor={String}
          numColumns={2}
          contentContainerStyle={styles.list}
          renderItem={() => <SkeletonCard />}
          columnWrapperStyle={styles.row}
        />
      ) : dishes && dishes.length > 0 ? (
        <FlatList
          data={dishes}
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
          icon="restaurant-outline"
          title="Sin resultados"
          description={
            query ? `No encontramos platillos para "${query}".` : 'Esta categoría está vacía.'
          }
          actionLabel={query ? 'Limpiar búsqueda' : undefined}
          onAction={query ? () => setQuery('') : undefined}
        />
      )}

      <CartButton />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.base,
    paddingVertical: Spacing.sm,
    backgroundColor: Colors.background,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  title: { ...Typography.h3, color: Colors.text, fontWeight: '700', flex: 1, textAlign: 'center' },
  searchBar: { paddingHorizontal: Spacing.base, paddingVertical: Spacing.sm },
  list: { paddingHorizontal: Spacing.base, paddingBottom: 120 },
  row: { justifyContent: 'space-between', marginBottom: Spacing.sm },
});
