import React, { useState, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  FlatList,
  TouchableOpacity,
  SafeAreaView,
  StatusBar,
  Dimensions,
  Animated,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import { useUserStore } from '../../store/user.store';
import { useBranchStore } from '../../store/branch.store';
import { useBranches } from '../../hooks/useBranches';
import { useFeaturedDishes, useCategories } from '../../hooks/useMenu';
import { Colors } from '../../theme';
import { BannerCarousel } from '../../components/shared/BannerCarousel';
import { CategoryCard } from '../../components/cards/CategoryCard';
import { ProductCard } from '../../components/cards/ProductCard';
import { SearchBar } from '../../components/ui/SearchBar';
import { CartButton } from '../../components/shared/CartButton';
import { Skeleton } from '../../components/ui/Skeleton';
import type { Platillo, Categoria } from '@amare/types';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

const FEATURED_CARD_WIDTH = SCREEN_WIDTH * 0.75; 
const FEATURED_GAP = 16;
const FEATURED_SNAP_INTERVAL = FEATURED_CARD_WIDTH + FEATURED_GAP;
const FEATURED_INSET = (SCREEN_WIDTH - FEATURED_CARD_WIDTH) / 2;

const HOME_BANNERS = [
  {
    id: '1',
    titulo: '¡2x1 en Pizzas!',
    subtitulo: 'Aprovecha todos los martes y jueves en sucursal.',
    imagen: 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=800',
    deepLink: '/promotions',
  },
  {
    id: '2',
    titulo: 'Envío Gratis',
    subtitulo: 'En tu primer pedido mayor a $350 MXN.',
    imagen: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRbwIYrgWuk2sbrX9QAJWIixayxHtlH2f9s8Q&s',
    deepLink: '/(tabs)/index',
  },
  {
    id: '3',
    titulo: 'Nuevos Postres',
    subtitulo: 'Descubre nuestra selección de repostería artesanal.',
    imagen: 'https://images.unsplash.com/photo-1551024601-bec78aea704b?auto=format&fit=crop&w=800',
    deepLink: '/(tabs)/index',
  },
];

export default function HomeScreen() {
  const router = useRouter();
  const user = useUserStore((s) => s.user);
  const { seleccionada: branch } = useBranchStore();
  const [search, setSearch] = useState('');

  // 🎞️ Animación para los indicadores (dots)
  const scrollX = useRef(new Animated.Value(0)).current;

  // 🔥 CRITICAL: Invocar este hook para que sincronice las sucursales desde el API
  // al Zustand store. Sin esto, autoSeleccionarSiUnica() nunca se ejecuta y
  // restauranteId queda undefined → las queries del menú nunca se habilitan.
  useBranches();

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
      params: { 
        id: String(cat.id), 
        restauranteId: String(restauranteId),
        nombre: cat.nombre // Enviamos el nombre para que el título no sea genérico
      },
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
            {user?.foto_url ? (
              <Image source={{ uri: user.foto_url }} style={styles.miniAvatarImg} />
            ) : (
              <Text style={styles.avatarText}>{user?.nombre?.[0] ?? '?'}</Text>
            )}
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
          <BannerCarousel items={HOME_BANNERS} />
        </View>

        {/* CATEGORÍAS */}
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Explorar menú</Text>
        </View>
        {loadingCats ? (
          <View style={[styles.horizontalList, { paddingHorizontal: 20 }]}>
            {[1, 2, 3].map((i) => <Skeleton key={i} width={140} height={100} borderRadius={20} style={{marginRight: 12}} />)}
          </View>
        ) : (
          <FlatList
            horizontal
            data={categories}
            keyExtractor={(item) => item.id.toString()}
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={[styles.horizontalList, { paddingHorizontal: 20 }]}
            renderItem={({ item }) => <CategoryCard categoria={item} onPress={handleCategory} />}
            // CategoryCard ya incluye un margen derecho interno por defecto en su componente
          />
        )}

        {/* DESTACADOS */}
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Los más pedidos </Text>
          <TouchableOpacity 
            onPress={() => {
              if (!restauranteId) return;
              router.push({
                pathname: '/category/[id]',
                params: { 
                  id: 'destacados', // O el ID específico que manejes para los destacados
                  restauranteId: String(restauranteId),
                  nombre: 'Los más pedidos'
                }
              });
            }}
          >
            <Text style={styles.seeAll}>Ver todo</Text>
          </TouchableOpacity>
        </View>
        
        {loadingFeatured ? (
          <View style={[styles.horizontalList, { paddingHorizontal: FEATURED_INSET }]}>
            {[1, 2].map((i) => <Skeleton key={i} width={FEATURED_CARD_WIDTH} height={260} borderRadius={25} style={{marginRight: FEATURED_GAP}} />)}
          </View>
        ) : (
          <View>
            <Animated.FlatList
              horizontal
              data={featured}
              keyExtractor={(item) => item.id.toString()}
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={[styles.horizontalList, { paddingHorizontal: FEATURED_INSET }]}
              renderItem={({ item }) => <ProductCard platillo={item} onPress={handleDish} width={FEATURED_CARD_WIDTH} />}
              ItemSeparatorComponent={() => <View style={{ width: FEATURED_GAP }} />}
              snapToInterval={FEATURED_SNAP_INTERVAL}
              snapToAlignment="center"
              decelerationRate="fast"
              onScroll={Animated.event(
                [{ nativeEvent: { contentOffset: { x: scrollX } } }],
                { useNativeDriver: false }
              )}
              scrollEventThrottle={16}
            />
            
            {/* 🔘 INDICADORES (DOTS) */}
            <View style={styles.pagination}>
              {featured?.map((_, i) => {
                const inputRange = [(i - 1) * FEATURED_SNAP_INTERVAL, i * FEATURED_SNAP_INTERVAL, (i + 1) * FEATURED_SNAP_INTERVAL];
                
                const dotWidth = scrollX.interpolate({
                  inputRange,
                  outputRange: [8, 20, 8],
                  extrapolate: 'clamp',
                });
                
                const opacity = scrollX.interpolate({
                  inputRange,
                  outputRange: [0.3, 1, 0.3],
                  extrapolate: 'clamp',
                });

                return <Animated.View key={i} style={[styles.dot, { width: dotWidth, opacity }]} />;
              })}
            </View>
          </View>
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
    paddingVertical: 12,
    backgroundColor: '#FFF',
    
    zIndex: 50, 
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.03,
    shadowRadius: 10,
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
    overflow: 'hidden',
  },
  miniAvatarImg: {
    width: '100%',
    height: '100%',
  },
  avatarText: { fontWeight: '800', color: Colors.primary },

  scrollContent: { 
    paddingTop: 10,
    paddingBottom: 130, 
  },

  welcomeSection: {
    paddingHorizontal: 20,
    marginTop: 10,
    marginBottom: 20,
  },
  greetingText: {
    fontSize: 26,
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
    marginTop: 32,
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
    paddingVertical: 10, // Importante: Da aire para que las sombras no se corten
  },
  pagination: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 15,
    gap: 6,
  },
  dot: {
    height: 8,
    borderRadius: 4,
    backgroundColor: Colors.primary,
  },
});