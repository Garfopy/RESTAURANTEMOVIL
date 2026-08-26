import React, { useState, useRef, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  FlatList,
  TouchableOpacity,
  StatusBar,
  Animated,
  RefreshControl,
  useWindowDimensions,
} from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { useUserStore } from '../../store/user.store';
import { useBranchConfigStore, useBranchStore } from '../../store/branch.store';
import { useCartStore } from '../../store/cart.store';
import { useBranches } from '../../hooks/useBranches';
import { useCategories, useDishes } from '../../hooks/useMenu';
import { requireAuth, saveAuthReturnTo } from '../../services/auth-gate.service';
import { Colors } from '../../theme';
import { BannerCarousel } from '../../components/shared/BannerCarousel';
import { CategoryCard } from '../../components/cards/CategoryCard';
import { ProductCard } from '../../components/cards/ProductCard';
import { SearchBar } from '../../components/ui/SearchBar';
import { StoreFAB } from '../../components/shared/StoreFAB';
import { Skeleton } from '../../components/ui/Skeleton';
import { useToast } from '../../context/ToastContext';
import { DEFAULT_RESTAURANT_ID } from '../../constants/branding';
import type { Platillo, Categoria } from '@amare/types';

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
    titulo: 'Nueva Dulcería',
    subtitulo: 'Descubre nuestra selección de repostería artesanal.',
    imagen: 'https://images.unsplash.com/photo-1551024601-bec78aea704b?auto=format&fit=crop&w=800',
    deepLink: '/(tabs)/index',
  },
];


export default function HomeScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const user = useUserStore((s) => s.user);
  const token = useUserStore((s) => s.token);
  const toast = useToast();
  const { seleccionada: branch, sucursales } = useBranchStore();
  const { tipoPedido, setTipoPedido } = useCartStore();

  const [search, setSearch] = useState('');
  const [refreshingHome, setRefreshingHome] = useState(false);
  const initialFlowStartedRef = useRef(false);
  const homeIntro = useRef(new Animated.Value(0)).current;
  const heroGlow = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.spring(homeIntro, { toValue: 1, damping: 19, stiffness: 95, useNativeDriver: true }).start();
    Animated.loop(
      Animated.sequence([
        Animated.timing(heroGlow, { toValue: 1, duration: 2400, useNativeDriver: true }),
        Animated.timing(heroGlow, { toValue: 0, duration: 2400, useNativeDriver: true }),
      ])
    ).start();
  }, [heroGlow, homeIntro]);

  // 🎞️ Animación para los indicadores (dots)
  const scrollX = useRef(new Animated.Value(0)).current;
  const featuredGap = 12;
  const featuredInset = width < 380 ? 16 : 20;
  const availableFeaturedWidth = Math.max(width - featuredInset * 2, 280);
  const featuredCardWidth = Math.min(Math.max(availableFeaturedWidth * 0.78, 240), 320);
  const featuredSnapInterval = featuredCardWidth + featuredGap;

  // 🔥 CRITICAL: Invocar este hook para que sincronice las sucursales desde el API
  // al Zustand store. Sin esto, autoSeleccionarSiUnica() nunca se ejecuta y
  // restauranteId queda undefined → las queries del menú nunca se habilitan.
  useBranches();

  const menuBranch = branch ?? sucursales[0] ?? null;
  // Si /branches falla o aún no responde, igual mostramos el menú del
  // único restaurante que existe en vez de dejar la pantalla vacía.
  const restauranteId = menuBranch?.id ?? user?.current_restaurante_id ?? DEFAULT_RESTAURANT_ID;
  const { data: categories, isLoading: loadingCats, refetch: refetchCategories } = useCategories(restauranteId);
  const { data: allDishes, isLoading: loadingAllDishes, refetch: refetchAllDishes } = useDishes(restauranteId);
  const refreshBranchConfig = useBranchConfigStore((state) => state.refresh);
  const visibleCategories =
    categories && categories.length > 0
      ? categories
      : Array.from(
          new Map(
            (allDishes ?? [])
              .filter((item) => item.categoria_id && item.categoria_nombre)
              .map((item) => [
                Number(item.categoria_id),
                {
                  id: Number(item.categoria_id),
                  nombre: item.categoria_nombre ?? 'Menu',
                  descripcion: null,
                  imagen: item.imagen ?? null,
                  orden: 0,
                  activo: true,
                  total_platillos: 0,
                } as Categoria,
              ])
          ).values()
        );
  const visibleFeatured = (allDishes ?? []).slice(0, 8);
  const loadingMenuItems = loadingAllDishes;

  async function refreshHome() {
    setRefreshingHome(true);
    try {
      await Promise.all([
        restauranteId ? refreshBranchConfig(restauranteId, { force: true }) : Promise.resolve(),
        refetchCategories(),
        refetchAllDishes(),
      ]);
    } catch {
      toast.warning('No pudimos actualizar el menú. Conservamos la última versión disponible.');
    } finally {
      setRefreshingHome(false);
    }
  }

  // UTEQ Cafetería solo maneja pedidos para recoger en sucursal, así que
  // el tipo de pedido se fija en silencio y nunca se le pregunta al usuario.
  // No depende de /branches: aunque esa llamada falle, el pedido igual debe
  // quedar en "pickup" para que el checkout funcione.
  useEffect(() => {
    if (tipoPedido || initialFlowStartedRef.current) {
      return;
    }
    initialFlowStartedRef.current = true;
    setTipoPedido('pickup');
  }, [tipoPedido, setTipoPedido]);

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
    const productRestaurantId = platillo.restaurante_id ?? restauranteId;
    router.push({
      pathname: '/product/[id]',
      params: { id: String(platillo.id), restauranteId: String(productRestaurantId) },
    });
  }

  function openCreateAccountFromHome() {
    void saveAuthReturnTo('/(tabs)');
    router.push({ pathname: '/(auth)/register', params: { returnTo: '/(tabs)' } } as never);
  }

  function openPublicPromotions() {
    router.push('/(tabs)/promotions' as never);
  }

  const firstName = token ? user?.nombre?.split(' ')[0] ?? '' : '';
  const currentHour = new Date().getHours();
  const greeting = currentHour < 12 ? 'Buenos días' : currentHour < 19 ? 'Buenas tardes' : 'Buenas noches';

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <StatusBar barStyle="dark-content" backgroundColor="#FAF8F4" />
      
      {/* HEADER ULTRA-SLIM CORREGIDO */}
      <Animated.View style={[styles.topNav, {
        opacity: homeIntro,
        transform: [{ translateY: homeIntro.interpolate({ inputRange: [0, 1], outputRange: [-14, 0] }) }],
      }]}>
        <View style={styles.locationSelector}>
          <View style={styles.locationIcon}>
            <Ionicons name="cafe-outline" size={17} color="#F5EFE4" />
          </View>
          <Text style={styles.locationName} numberOfLines={1}>
            UTEQ Cafetería
          </Text>
        </View>

        <TouchableOpacity 
          onPress={() => {
            if (!requireAuth(router, {
              message: 'Crea tu cuenta para ver beneficios, direcciones guardadas y actividad.',
              returnTo: '/(tabs)/profile',
            })) return;
            router.push('/(tabs)/profile');
          }}
          style={styles.profileTrigger}
          activeOpacity={0.7}
        >
          <View style={styles.miniAvatar}>
            {user?.foto_url ? (
              <Image source={{ uri: user.foto_url }} style={styles.miniAvatarImg} />
            ) : (
              token ? (
                <Text style={styles.avatarText}>{user?.nombre?.[0] ?? '?'}</Text>
              ) : (
                <Ionicons name="person-outline" size={20} color={Colors.primary} />
              )
            )}
          </View>
        </TouchableOpacity>
      </Animated.View>

      <ScrollView 
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.scrollContent}
        refreshControl={<RefreshControl refreshing={refreshingHome} onRefresh={() => void refreshHome()} tintColor={Colors.primary} />}
      >
        {/* SALUDO Y BUSCADOR */}
        <Animated.View style={[styles.welcomeSection, {
          opacity: homeIntro,
          transform: [{ translateY: homeIntro.interpolate({ inputRange: [0, 1], outputRange: [22, 0] }) }],
        }]}>
          <LinearGradient colors={['#202228', '#30333B']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.heroCard}>
            <Animated.View pointerEvents="none" style={[styles.heroGlow, {
              opacity: heroGlow.interpolate({ inputRange: [0, 1], outputRange: [0.12, 0.28] }),
              transform: [{ scale: heroGlow.interpolate({ inputRange: [0, 1], outputRange: [0.9, 1.12] }) }],
            }]} />
            <View>
              <Text style={styles.heroEyebrow}>{greeting.toUpperCase()}</Text>
              <Text style={styles.greetingText}>{firstName ? `${firstName},` : 'Bienvenido,'}</Text>
            </View>
            <Text style={styles.subtitleText}>Hoy puede empezar con algo delicioso.</Text>

            <View style={styles.searchContainer}>
              <SearchBar
                value={search}
                onChangeText={setSearch}
                onSubmit={handleSearch}
                placeholder="Buscar platillos, bebidas y más"
              />
            </View>

            <View style={styles.heroFooter}>
              <View style={styles.heroFeature}>
                <Ionicons name="sparkles-outline" size={14} color="#D7C6A8" />
                <Text style={styles.heroFeatureText}>Selección especial</Text>
              </View>
              <View style={styles.heroDivider} />
              <View style={styles.heroFeature}>
                <Ionicons name="time-outline" size={14} color="#D7C6A8" />
                <Text style={styles.heroFeatureText}>Pedido sencillo</Text>
              </View>
            </View>

            {!token ? (
              <View style={styles.guestAccountStrip}>
                <View style={styles.guestAccountCopy}>
                  <Text style={styles.guestAccountTitle}>Ofertas y pedidos guardados</Text>
                  <Text style={styles.guestAccountText}>Crea tu cuenta cuando quieras usar beneficios.</Text>
                </View>
                <View style={styles.guestAccountActions}>
                  <TouchableOpacity style={styles.guestAccountPrimary} onPress={openCreateAccountFromHome} activeOpacity={0.86}>
                    <Text style={styles.guestAccountPrimaryText}>Crear cuenta</Text>
                  </TouchableOpacity>
                  <TouchableOpacity style={styles.guestAccountIconButton} onPress={openPublicPromotions} activeOpacity={0.86}>
                    <Ionicons name="pricetag-outline" size={16} color="#E9DDC8" />
                  </TouchableOpacity>
                </View>
              </View>
            ) : null}
          </LinearGradient>
        </Animated.View>

        {/* BANNERS */}
        <Animated.View style={[styles.bannerSection, { opacity: homeIntro }]}>
          <BannerCarousel items={HOME_BANNERS} />
        </Animated.View>

        {/* CATEGORÍAS */}
        <View style={styles.sectionHeader}>
          <View>
            <Text style={styles.sectionKicker}>DESCUBRE</Text>
            <Text style={styles.sectionTitle}>Explorar menú</Text>
          </View>
          <View style={styles.sectionIcon}><Ionicons name="grid-outline" size={17} color={Colors.primary} /></View>
        </View>
        {loadingCats && visibleCategories.length === 0 ? (
          <View style={[styles.horizontalList, { paddingHorizontal: featuredInset }]}>
            {[1, 2, 3].map((i) => <Skeleton key={i} width={140} height={100} borderRadius={20} style={{marginRight: 12}} />)}
          </View>
        ) : (
          <FlatList
            horizontal
            data={visibleCategories}
            keyExtractor={(item) => item.id.toString()}
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={[styles.horizontalList, { paddingHorizontal: featuredInset }]}
            renderItem={({ item }) => <CategoryCard categoria={item} onPress={handleCategory} />}
            removeClippedSubviews={false}
            // CategoryCard ya incluye un margen derecho interno por defecto en su componente
          />
        )}

        {/* DESTACADOS */}
        <View style={styles.sectionHeader}>
          <View>
            <Text style={styles.sectionKicker}>FAVORITOS</Text>
            <Text style={styles.sectionTitle}>Los más pedidos</Text>
          </View>
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
        
        {loadingMenuItems ? (
          <View style={[styles.horizontalList, { paddingHorizontal: featuredInset }]}>
            {[1, 2].map((i) => <Skeleton key={i} width={featuredCardWidth} height={260} borderRadius={25} style={{marginRight: featuredGap}} />)}
          </View>
        ) : visibleFeatured.length > 0 ? (
          <View>
            <Animated.FlatList
              horizontal
              data={visibleFeatured}
              keyExtractor={(item) => item.id.toString()}
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={[styles.horizontalList, { paddingHorizontal: featuredInset }]}
              renderItem={({ item }) => <ProductCard platillo={item} onPress={handleDish} width={featuredCardWidth} />}
              ItemSeparatorComponent={() => <View style={{ width: featuredGap }} />}
              snapToInterval={featuredSnapInterval}
              snapToAlignment="start"
              decelerationRate="fast"
              bounces={false}
              removeClippedSubviews={false}
              onScroll={Animated.event(
                [{ nativeEvent: { contentOffset: { x: scrollX } } }],
                { useNativeDriver: false }
              )}
              scrollEventThrottle={16}
            />
            
            {/* 🔘 INDICADORES (DOTS) */}
            <View style={styles.pagination}>
              {visibleFeatured.map((_, i) => {
                const inputRange = [(i - 1) * featuredSnapInterval, i * featuredSnapInterval, (i + 1) * featuredSnapInterval];
                
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
        ) : (
          <View style={[styles.emptyMenuBox, { marginHorizontal: featuredInset }]}>
            <Ionicons name="restaurant-outline" size={22} color="#8A7A64" />
            <Text style={styles.emptyMenuText}>No pudimos cargar platillos para esta sucursal. Desliza para actualizar.</Text>
          </View>
        )}

        {/* Espacio final interno para empujar el contenido arriba del dock */}
        <View style={styles.bottomSpacer} />
      </ScrollView>

      {/* BOTÓN FLOTANTE DE TIENDA */}
      <StoreFAB />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#FAF8F4' },
  topNav: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingTop: 10,
    paddingBottom: 13,
    backgroundColor: '#FAF8F4',
    zIndex: 50,
  },
  locationSelector: { flexDirection: 'row', alignItems: 'center', flex: 1, minWidth: 0, paddingVertical: 4 },
  locationIcon: { width: 38, height: 38, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: '#25282E' },
  locationName: { fontSize: 15, fontWeight: '900', color: '#24262B', marginLeft: 10, flexShrink: 1 },
  profileTrigger: { marginLeft: 15, padding: 2 },
  miniAvatar: { width: 40, height: 40, borderRadius: 15, backgroundColor: '#EEE9E0', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: '#DED7CC', overflow: 'hidden' },
  miniAvatarImg: { width: '100%', height: '100%' },
  avatarText: { fontWeight: '900', color: Colors.primary },

  scrollContent: { paddingTop: 4, paddingBottom: 176 },
  welcomeSection: { paddingHorizontal: 16, marginTop: 6, marginBottom: 8 },
  heroCard: {
    borderRadius: 30,
    paddingHorizontal: 20,
    paddingTop: 22,
    paddingBottom: 16,
    overflow: 'hidden',
    shadowColor: '#121317',
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.2,
    shadowRadius: 22,
    elevation: 8,
  },
  heroGlow: { position: 'absolute', width: 210, height: 210, borderRadius: 105, backgroundColor: '#D0B17D', top: -130, right: -70 },
  heroEyebrow: { color: '#BBAE98', fontSize: 9, fontWeight: '900', letterSpacing: 1.8, marginBottom: 3 },
  greetingText: { fontFamily: 'PlayfairDisplay_700Bold', fontSize: 31, color: '#F7F1E7', letterSpacing: -0.6 },
  subtitleText: { fontSize: 13, color: '#BEB6A9', marginTop: 4 },
  searchContainer: { marginTop: 18 },
  heroFooter: { flexDirection: 'row', alignItems: 'center', marginTop: 14, paddingHorizontal: 3 },
  heroFeature: { flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6 },
  heroFeatureText: { color: '#AAA294', fontSize: 10, fontWeight: '700' },
  heroDivider: { width: 1, height: 15, backgroundColor: 'rgba(233,221,200,0.14)' },
  guestAccountStrip: {
    marginTop: 14,
    padding: 12,
    borderRadius: 18,
    backgroundColor: 'rgba(255,255,255,0.07)',
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.12)',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  guestAccountCopy: {
    flex: 1,
    minWidth: 0,
  },
  guestAccountTitle: {
    color: '#F7F1E7',
    fontSize: 12,
    fontWeight: '900',
  },
  guestAccountText: {
    color: '#BEB6A9',
    fontSize: 10,
    lineHeight: 14,
    marginTop: 2,
  },
  guestAccountActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  guestAccountPrimary: {
    minHeight: 34,
    borderRadius: 17,
    paddingHorizontal: 12,
    backgroundColor: '#E9DDC8',
    alignItems: 'center',
    justifyContent: 'center',
  },
  guestAccountPrimaryText: {
    color: '#202228',
    fontSize: 10,
    fontWeight: '900',
  },
  guestAccountIconButton: {
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.18)',
    backgroundColor: 'rgba(233,221,200,0.08)',
  },

  bannerSection: { marginTop: 20 },

  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 20,
    marginTop: 30,
    marginBottom: 12,
  },
  sectionKicker: { color: '#A18D6E', fontSize: 9, fontWeight: '900', letterSpacing: 1.8, marginBottom: 3 },
  sectionTitle: {
    fontFamily: 'PlayfairDisplay_700Bold',
    fontSize: 22,
    color: '#25272C',
    letterSpacing: -0.4,
  },
  sectionIcon: { width: 38, height: 38, borderRadius: 14, backgroundColor: '#EEE8DE', alignItems: 'center', justifyContent: 'center' },
  seeAll: {
    color: '#7C6748',
    fontWeight: '900',
    fontSize: 12,
    paddingVertical: 8,
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
  emptyMenuBox: {
    minHeight: 118,
    borderRadius: 18,
    backgroundColor: '#F1ECE4',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 20,
    gap: 8,
  },
  emptyMenuText: {
    color: '#7C6748',
    fontSize: 13,
    fontWeight: '700',
    textAlign: 'center',
  },
  bottomSpacer: {
    height: 24,
  },
});
