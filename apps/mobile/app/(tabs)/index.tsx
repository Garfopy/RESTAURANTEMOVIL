import React, { useState, useEffect, useRef } from 'react';
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
  const { seleccionada: branch } = useBranchStore();
  const { setTipoPedido, tipoPedido } = useCartStore();
  const [search, setSearch] = useState('');

  const { data: branches, isLoading: loadingBranches } = useBranches();
  const restauranteId = branch?.id;
  const redirectedToBranchSelector = useRef(false);

  // Si las sucursales cargaron pero ninguna está seleccionada (más de una disponible), ir al selector
  useEffect(() => {
    if (loadingBranches || !branches?.length || branch || redirectedToBranchSelector.current) return;
    if (branches.length > 1) {
      redirectedToBranchSelector.current = true;
      router.push('/branch-selector');
    }
  }, [loadingBranches, branches, branch]);

  // Resetear el ref cuando el usuario selecciona sucursal (para permitir re-redirigir si hace logout)
  useEffect(() => {
    if (branch) redirectedToBranchSelector.current = false;
  }, [branch]);

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
    <View style={styles.cardSection}>
      <Text style={styles.sectionTitle}>Categorías</Text>

      {loadingCats ? (
        <FlatList
          horizontal
          data={[1, 2, 3]}
          keyExtractor={String}
          renderItem={() => (
            <Skeleton
              width={140}
              height={100}
              borderRadius={14}
              style={{ marginRight: 10 }}
            />
          )}
          scrollEnabled={false}
        />
      ) : (
        <FlatList
          horizontal
          data={categories}
          keyExtractor={(c) => String(c.id)}
          renderItem={({ item }) => (
            <CategoryCard
              categoria={item}
              onPress={handleCategory}
            />
          )}
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={{
            paddingRight: Spacing.base,
          }}
        />
      )}
    </View>
  </View>

{/* Destacados */}
<View style={styles.section}>
  <View style={styles.cardSection}>
    <Text style={styles.sectionTitle}>🔥 Más pedidos</Text>

    {loadingFeatured ? (
      <FlatList
        horizontal
        data={[1, 2, 3]}
        keyExtractor={String}
        renderItem={() => (
          <Skeleton
            width={180}
            height={220}
            borderRadius={14}
            style={{ marginRight: 10 }}
          />
        )}
        scrollEnabled={false}
      />
    ) : (
      <FlatList
        horizontal
        data={featured}
        keyExtractor={(p) => String(p.id)}
        renderItem={({ item }) => (
          <ProductCard
            platillo={item}
            onPress={handleDish}
          />
        )}
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={{
          paddingRight: Spacing.base,
        }}
      />
    )}
  </View>
</View>

        <View style={{ height: 120 }} />
      </ScrollView>

      <CartButton />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: '#F6F7FB',
  },

  content: {
    paddingBottom: 120,
  },

  // HEADER PREMIUM
  header: {
    backgroundColor: Colors.primary,
    paddingHorizontal: 24,
    paddingTop: 16,
    paddingBottom: 16,
    borderBottomLeftRadius: 12,
    borderBottomRightRadius: 12,

    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 8,
    },
    shadowOpacity: 0.12,
    shadowRadius: 18,
    elevation: 10,
  },

  greeting: {
    fontSize: 28,
    fontWeight: '800',
    color: '#FFF',
    letterSpacing: -0.5,
  },

  branchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',

    marginTop: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,

    borderRadius: 50,
    backgroundColor: 'rgba(255,255,255,0.15)',
  },

  branchName: {
    color: '#FFF',
    fontSize: 13,
    fontWeight: '600',
    marginHorizontal: 4,
  },

  avatar: {
    width: 52,
    height: 52,
    borderRadius: 26,

    backgroundColor: Colors.accent,

    justifyContent: 'center',
    alignItems: 'center',

    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 6,
    },
    shadowOpacity: 0.18,
    shadowRadius: 10,
    elevation: 8,
  },

  avatarLetter: {
    color: '#FFF',
    fontWeight: '800',
    fontSize: 20,
  },

  // SECCIONES
  section: {
    paddingHorizontal: 20,
    marginTop: 28,
  },

  sectionNoPad: {
    marginTop: 22,
  },

  sectionTitle: {
    fontSize: 24,
    fontWeight: '800',
    color: '#111827',
    marginBottom: 16,
    letterSpacing: -0.5,
  },

cardSection: {
  backgroundColor: '#FFFFFF',
  borderRadius: 24,
  paddingVertical: 18,
  paddingLeft: 16,

  shadowColor: '#000',
  shadowOffset: {
    width: 0,
    height: 4,
  },
  shadowOpacity: 0.06,
  shadowRadius: 12,
  elevation: 4,
},
});
