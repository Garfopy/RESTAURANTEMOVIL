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
import { Colors } from '../../theme';
import { BannerCarousel } from '../../components/shared/BannerCarousel';
import { CategoryCard } from '../../components/cards/CategoryCard';
import { ProductCard } from '../../components/cards/ProductCard';
import { SearchBar } from '../../components/ui/SearchBar';
import { OrderTypeSelector } from '../../components/shared/OrderTypeSelector';
import { CartButton } from '../../components/shared/CartButton';
import { Skeleton } from '../../components/ui/Skeleton';
import type { Platillo, Categoria } from '@amare/types';

export default function HomeScreen() {
  const router = useRouter();
  const user = useUserStore((s) => s.user);
  const { seleccionada: branch } = useBranchStore();
  const { tipoPedido, setTipoPedido } = useCartStore();
  const [search, setSearch] = useState('');
  
  const restauranteId = branch?.id;
  const { data: categories, isLoading: loadingCats } = useCategories(restauranteId);
  const { data: featured, isLoading: loadingFeatured } = useFeaturedDishes(restauranteId);

  function handleSearch() {
    if (search.trim() && restauranteId) {
      router.push({ 
        pathname: '/category/[id]', 
        params: { id: 'search', q: search.trim(), restauranteId: String(restauranteId) } 
      });
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

  return (
    <SafeAreaView style={styles.safe}>
      <StatusBar barStyle="dark-content" backgroundColor="#FFF" />
      
      {/* HEADER ULTRA-SLIM CORREGIDO */}
      <View style={styles.topNav}>
        <TouchableOpacity 
          style={styles.locationSelector}
          onPress={() => router.push('/branch-selector')}
          activeOpacity={0.7}
        >
          <Ionicons name="location" size={18} color={Colors.primary} />
          <View style={{ marginLeft: 8 }}>
            <Text style={styles.locationLabel}>Entregar en</Text>
            <View style={styles.row}>
              <Text style={styles.locationName} numberOfLines={1}>
                {branch?.nombre ?? 'Seleccionar sucursal'}
              </Text>
              <Ionicons name="chevron-down" size={14} color="#6B7280" />
            </View>
          </View>
        </TouchableOpacity>

        <TouchableOpacity 
          onPress={() => router.push('/(tabs)/profile')}
          style={styles.profileTrigger}
          activeOpacity={0.7}
        >
          <View style={styles.miniAvatar}>
            <Text style={styles.avatarText}>{user?.nombre?.[0] ?? '?'}</Text>
          </View>
        </TouchableOpacity>
      </View>

      <ScrollView 
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.scrollContent}
      >
        {/* SALUDO Y BUSCADOR */}
        <View style={styles.welcomeSection}>
          <Text style={styles.greetingText}>
            {user?.nombre ? `Hola, ${user.nombre.split(' ')[0]}` : 'Bienvenido'} 
          </Text>
          <Text style={styles.subtitleText}>¿Qué se te antoja hoy?</Text>
          
          <View style={styles.searchContainer}>
            <SearchBar
              value={search}
              onChangeText={setSearch}
              onSubmit={handleSearch}
              placeholder="Busca tu platillo favorito..."
            />
          </View>
        </View>

        {/* BANNERS */}
        <View style={styles.bannerSection}>
          <BannerCarousel items={[]} />
        </View>

        {/* CATEGORÍAS */}
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Explorar menú</Text>
        </View>
        {loadingCats ? (
          <View style={styles.horizontalList}>
            {[1, 2, 3].map((i) => <Skeleton key={i} width={100} height={100} borderRadius={20} style={{marginRight: 12}} />)}
          </View>
        ) : (
          <FlatList
            horizontal
            data={categories}
            keyExtractor={(item) => item.id.toString()}
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.horizontalList}
            renderItem={({ item }) => (
              <CategoryCard categoria={item} onPress={handleCategory} />
            )}
          />
        )}

        {/* DESTACADOS */}
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Los más pedidos </Text>
          <TouchableOpacity>
            <Text style={styles.seeAll}>Ver todo</Text>
          </TouchableOpacity>
        </View>
        
        {loadingFeatured ? (
          <View style={styles.horizontalList}>
            {[1, 2].map((i) => <Skeleton key={i} width={200} height={250} borderRadius={25} style={{marginRight: 15}} />)}
          </View>
        ) : (
          <FlatList
            horizontal
            data={featured}
            keyExtractor={(item) => item.id.toString()}
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.horizontalList}
            renderItem={({ item }) => (
              <ProductCard platillo={item} onPress={handleDish} />
            )}
          />
        )}

        {/* Espacio final interno para empujar el contenido arriba del dock */}
        <View style={{ height: 40 }} />
      </ScrollView>

      {/* BOTÓN DEL CARRITO ORIGINAL */}
      <CartButton />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#FFFFFF' },
  
  topNav: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingVertical: 10,
    backgroundColor: '#FFF',
    
    // 🌟 CORRECCIÓN MAESTRA: Asegura que el header reciba los clics en iOS/Android
    // evitando bloqueos de capas absolutas transparentes
    zIndex: 50, 
    elevation: 5, 
  },
  locationSelector: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    paddingVertical: 4, // Área de toque expandida
  },
  locationLabel: {
    fontSize: 11,
    color: '#9CA3AF',
    fontWeight: '600',
    textTransform: 'uppercase',
  },
  locationName: {
    fontSize: 14,
    fontWeight: '700',
    color: '#111827',
    marginRight: 4,
  },
  row: { flexDirection: 'row', alignItems: 'center' },
  profileTrigger: {
    marginLeft: 15,
    padding: 2,
  },
  miniAvatar: {
    width: 38,
    height: 38,
    borderRadius: 12,
    backgroundColor: '#F3F4F6',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  avatarText: { fontWeight: '800', color: Colors.primary },

  scrollContent: { 
    paddingTop: 10,
    paddingBottom: 130, 
  },

  welcomeSection: {
    paddingHorizontal: 20,
    marginVertical: 15,
  },
  greetingText: {
    fontSize: 24,
    fontWeight: '800',
    color: '#111827',
    letterSpacing: -0.5,
  },
  subtitleText: {
    fontSize: 15,
    color: '#6B7280',
    marginTop: 2,
  },
  searchContainer: {
    marginTop: 20,
  },

  orderTypeWrapper: {
    marginVertical: 10,
  },

  bannerSection: {
    marginTop: 10,
  },

  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 20,
    marginTop: 30,
    marginBottom: 15,
  },
  sectionTitle: {
    fontSize: 19,
    fontWeight: '800',
    color: '#111827',
    letterSpacing: -0.3,
  },
  seeAll: {
    color: Colors.primary,
    fontWeight: '700',
    fontSize: 13,
  },
  horizontalList: {
    paddingLeft: 20,
  },
});