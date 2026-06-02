import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  FlatList,
  TouchableOpacity,
  SafeAreaView,
  StatusBar,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useUserStore } from '../../store/user.store';
import { useBranchStore } from '../../store/branch.store';
import { useCartStore } from '../../store/cart.store';
import { useBranches } from '../../hooks/useBranches';
import { useFeaturedDishes, useCategories } from '../../hooks/useMenu';
import { Colors, Spacing, Typography } from '../../theme';
import { BannerCarousel } from '../../components/shared/BannerCarousel';
import { CategoryCard } from '../../components/cards/CategoryCard';
import { ProductCard } from '../../components/cards/ProductCard';
import { SearchBar } from '../../components/ui/SearchBar';
import { CartButton } from '../../components/shared/CartButton';
import { OrderTypeSelector } from '../../components/shared/OrderTypeSelector';
import { Skeleton } from '../../components/ui/Skeleton';
import type { Platillo, Categoria } from '@amare/types';

export default function HomeScreen() {
  const router = useRouter();
  const user = useUserStore((s) => s.user);
  const { seleccionada: branch, setSucursales } = useBranchStore();
  const { setTipoPedido, tipoPedido } = useCartStore();
  const [search, setSearch] = useState('');

  const { data: branches, isLoading: loadingBranches } = useBranches();
  const restauranteId = branch?.id;

  const { data: categories, isLoading: loadingCats } = useCategories(restauranteId);
  const { data: featured, isLoading: loadingFeatured } = useFeaturedDishes(restauranteId);

  function handleSearch() {
    if (search.trim() && restauranteId) {
      router.push({ pathname: '/category/[id]', params: { id: 'search', q: search.trim(), restauranteId: String(restauranteId) } });
    }
  }

  function handleCategory(cat: Categoria) {
    if (!restauranteId) return;
    router.push({
      pathname: '/category/[id]',
      params: { id: String(cat.id), restauranteId: String(restauranteId) },
    });
  }

  function handleDish(platillo: Platillo) {
    if (!restauranteId) return;
    router.push({
      pathname: '/product/[id]',
      params: { id: String(platillo.id), restauranteId: String(restauranteId) },
    });
  }

  const greeting = user?.nombre
    ? `Hola, ${user.nombre.split(' ')[0]} 👋`
    : 'Bienvenido 👋';

  return (
    <SafeAreaView style={styles.safe}>
      <StatusBar barStyle="light-content" backgroundColor={Colors.primary} />
      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        stickyHeaderIndices={[0]}
      >
        {/* Header */}
        <View style={styles.header}>
          <View>
            <Text style={styles.greeting}>{greeting}</Text>
            {branch && (
              <TouchableOpacity
                style={styles.branchRow}
                onPress={() => router.push('/branch-selector')}
              >
                <Ionicons name="location-outline" size={14} color={Colors.accent} />
                <Text style={styles.branchName}>{branch.nombre}</Text>
                <Ionicons name="chevron-down" size={14} color={Colors.accent} />
              </TouchableOpacity>
            )}
          </View>
          <TouchableOpacity onPress={() => router.push('/(tabs)/profile')}>
            <View style={styles.avatar}>
              <Text style={styles.avatarLetter}>
                {user?.nombre?.[0]?.toUpperCase() ?? '?'}
              </Text>
            </View>
          </TouchableOpacity>
        </View>

        {/* Search */}
        <View style={styles.section}>
          <SearchBar
            value={search}
            onChangeText={setSearch}
            onSubmit={handleSearch}
          />
        </View>

        {/* Order type */}
        <View style={styles.sectionNoPad}>
          <OrderTypeSelector value={tipoPedido} onChange={setTipoPedido} />
        </View>

        {/* Banners (usamos categorías como demo hasta tener real) */}
        <View style={styles.section}>
          <BannerCarousel items={[]} />
        </View>

        {/* Categorías */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Categorías</Text>
          {loadingCats ? (
            <FlatList
              horizontal
              data={[1, 2, 3]}
              keyExtractor={String}
              renderItem={() => (
                <Skeleton width={140} height={100} borderRadius={14} style={{ marginRight: 10 }} />
              )}
              scrollEnabled={false}
            />
          ) : (
            <FlatList
              horizontal
              data={categories}
              keyExtractor={(c) => String(c.id)}
              renderItem={({ item }) => (
                <CategoryCard categoria={item} onPress={handleCategory} />
              )}
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={{ paddingRight: Spacing.base }}
            />
          )}
        </View>

        {/* Destacados */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Más pedidos</Text>
          {loadingFeatured ? (
            <FlatList
              horizontal
              data={[1, 2, 3]}
              keyExtractor={String}
              renderItem={() => (
                <Skeleton width={180} height={220} borderRadius={14} style={{ marginRight: 10 }} />
              )}
              scrollEnabled={false}
            />
          ) : (
            <FlatList
              horizontal
              data={featured}
              keyExtractor={(p) => String(p.id)}
              renderItem={({ item }) => (
                <ProductCard platillo={item} onPress={handleDish} />
              )}
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={{ paddingRight: Spacing.base }}
            />
          )}
        </View>

        <View style={{ height: 120 }} />
      </ScrollView>

      <CartButton />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  content: { paddingBottom: Spacing.base },

  // Header
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    backgroundColor: Colors.primary,
    paddingHorizontal: Spacing.base,
    paddingVertical: Spacing.base,
  },
  greeting: {
    fontSize: 20,
    fontWeight: '700',
    color: Colors.white,
    marginBottom: 4,
  },
  branchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  branchName: { color: Colors.accent, fontSize: 13, fontWeight: '500' },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: Colors.accent,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarLetter: { color: Colors.white, fontWeight: '700', fontSize: 18 },

  // Sections
  section: { paddingHorizontal: Spacing.base, marginTop: Spacing.base },
  sectionNoPad: { marginTop: Spacing.base },
  sectionTitle: {
    ...Typography.h3,
    color: Colors.text,
    fontWeight: '700',
    marginBottom: Spacing.sm,
  },
});
