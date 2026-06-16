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
  Modal,
  ActivityIndicator,
  useWindowDimensions,
  Alert,
} from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import MapView, { Marker } from 'react-native-maps';
import { useUserStore } from '../../store/user.store';
import { useBranchStore } from '../../store/branch.store';
import { useCartStore } from '../../store/cart.store';
import { useTableSessionStore } from '../../store/table-session.store';
import { useBranches } from '../../hooks/useBranches';
import { useFeaturedDishes, useCategories } from '../../hooks/useMenu';
import { getNearestBranches } from '../../services/branches.service';
import { Colors } from '../../theme';
import { BannerCarousel } from '../../components/shared/BannerCarousel';
import { CategoryCard } from '../../components/cards/CategoryCard';
import { ProductCard } from '../../components/cards/ProductCard';
import { SearchBar } from '../../components/ui/SearchBar';
import { StoreFAB } from '../../components/shared/StoreFAB';
import { Skeleton } from '../../components/ui/Skeleton';
import { OrderTypeSelector } from '../../components/shared/OrderTypeSelector';
import type { Platillo, Categoria, TipoPedido, Sucursal } from '@amare/types';

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
  const { width } = useWindowDimensions();
  const user = useUserStore((s) => s.user);
  const { seleccionada: branch, sucursales, seleccionar } = useBranchStore();
  const { tipoPedido, setTipoPedido, itemCount, restauranteId: cartRestaurantId, clear } = useCartStore();
  const tableSession = useTableSessionStore((s) => s.session);
  
  const [showTypeModal, setShowTypeModal] = useState(false);
  const [availableTypes, setAvailableTypes] = useState<TipoPedido[]>(['delivery', 'pickup']);
  const [detectingLocation, setDetectingLocation] = useState(false);
  const [detectedCoords, setDetectedCoords] = useState<{ lat: number; lng: number } | null>(null);
  const [selectingPickupBranch, setSelectingPickupBranch] = useState(false);
  const [search, setSearch] = useState('');

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

  const restauranteId = branch?.id;
  const { data: categories, isLoading: loadingCats } = useCategories(restauranteId);
  const { data: featured, isLoading: loadingFeatured } = useFeaturedDishes(restauranteId);

  // Lógica para detectar ubicación y mostrar modal inicial
  useEffect(() => {
    if (tipoPedido || sucursales.length === 0) {
      return;
    }

    const enabledTypes = getEnabledOrderTypes(sucursales);

    if (enabledTypes.length === 1 && enabledTypes[0] === 'eat_in') {
      if (tableSession?.branch) {
        if (!branch || branch.id !== tableSession.branch.id) {
          seleccionar(tableSession.branch);
        }
        setTipoPedido('eat_in');
      } else {
        router.push({ pathname: '/table-scanner', params: { returnTo: '/(tabs)' } });
      }
      return;
    }

    setAvailableTypes(enabledTypes);
    openDeliveryFlow();
  }, [tipoPedido, sucursales, branch, seleccionar, setTipoPedido, tableSession, router]);

  async function openDeliveryFlow() {
    const enabledTypes = getEnabledOrderTypes(sucursales);
    setAvailableTypes(enabledTypes.length ? enabledTypes : ['delivery', 'pickup']);
    setSelectingPickupBranch(false);
    setShowTypeModal(true);
    setDetectingLocation(true);

    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status === 'granted') {
        const location = await Location.getCurrentPositionAsync({
          accuracy: Location.Accuracy.Balanced,
        });
        setDetectedCoords({
          lat: location.coords.latitude,
          lng: location.coords.longitude,
        });
      }
    } catch (error) {
      console.error('Error detectando ubicacion:', error);
    } finally {
      setDetectingLocation(false);
    }
  }

  function closeDeliveryFlow() {
    setShowTypeModal(false);
    setSelectingPickupBranch(false);
  }

  async function handleInitialTypeSelect(tipo: TipoPedido) {
    if (tipo === 'pickup') {
      setTipoPedido('pickup');
      setSelectingPickupBranch(true);
      return;
    }

    if (tipo === 'eat_in') {
      closeDeliveryFlow();
      router.push({ pathname: '/table-scanner', params: { returnTo: '/(tabs)' } });
      return;
    }

    setDetectingLocation(true);
    setTipoPedido(tipo);

    try {
      let targetBranch: Sucursal | undefined;

      if (detectedCoords) {
        const nearest = await getNearestBranches(detectedCoords.lat, detectedCoords.lng, tipo);
        targetBranch = nearest[0];
      }

      if (!targetBranch) {
        const compatible = sucursales.filter((item) => item.tipos_entrega.includes(tipo));
        if (compatible.length === 1) {
          targetBranch = compatible[0];
        }
      }

      if (targetBranch) {
        selectBranchForType(targetBranch, tipo);
        return;
      }

      closeDeliveryFlow();
      Alert.alert(
        'Selecciona sucursal',
        'No pudimos detectar una sucursal cercana para este tipo de pedido. Elige una manualmente.',
        [
          {
            text: 'Elegir sucursal',
            onPress: () => router.push({ pathname: '/branch-selector', params: { tipoPedido: tipo } }),
          },
        ]
      );
    } catch (error) {
      console.error('Error seleccionando sucursal cercana:', error);
      closeDeliveryFlow();
      router.push({ pathname: '/branch-selector', params: { tipoPedido: tipo } });
    } finally {
      setDetectingLocation(false);
    }
  }

  function selectBranchForType(nextBranch: Sucursal, tipo: TipoPedido) {
    const applySelection = () => {
      seleccionar(nextBranch);
      setTipoPedido(tipo);
      closeDeliveryFlow();
    };

    if (itemCount > 0 && cartRestaurantId !== null && cartRestaurantId !== nextBranch.id) {
      Alert.alert(
        'Cambiar sucursal',
        'Tu carrito tiene platillos de otra sucursal. Para cambiarla necesitamos vaciarlo.',
        [
          { text: 'Cancelar', style: 'cancel' },
          {
            text: 'Vaciar y cambiar',
            style: 'destructive',
            onPress: () => {
              clear();
              applySelection();
            },
          },
        ]
      );
      return;
    }

    applySelection();
  }

  function getEnabledOrderTypes(branches: Sucursal[]): TipoPedido[] {
    const order: TipoPedido[] = ['delivery', 'pickup', 'eat_in'];
    const found = new Set<TipoPedido>();

    branches.forEach((item) => {
      item.tipos_entrega?.forEach((type) => found.add(type));
    });

    return order.filter((type) => found.has(type));
  }

  function getOrderModeLabel() {
    if (tipoPedido === 'delivery') return 'Delivery';
    if (tipoPedido === 'pickup') return 'Pickup';
    if (tipoPedido === 'eat_in') return tableSession?.mesaLabel ? `Comer aqui · ${tableSession.mesaLabel}` : 'Comer aqui';
    return 'Elegir entrega';
  }

  function getOrderModeIcon(): keyof typeof Ionicons.glyphMap {
    if (tipoPedido === 'delivery') return 'bicycle-outline';
    if (tipoPedido === 'pickup') return 'bag-handle-outline';
    if (tipoPedido === 'eat_in') return 'restaurant-outline';
    return 'options-outline';
  }

  function getPickupBranches() {
    return sucursales
      .filter((item) => item.tipos_entrega?.includes('pickup'))
      .sort((a, b) => {
        const da = a.distancia_km ?? Number.MAX_SAFE_INTEGER;
        const db = b.distancia_km ?? Number.MAX_SAFE_INTEGER;
        return da - db;
      });
  }

  function getPickupMapRegion() {
    const pickupWithCoords = getPickupBranches().find((item) => item.lat != null && item.lng != null);
    return {
      latitude: detectedCoords?.lat ?? pickupWithCoords?.lat ?? 20.591403,
      longitude: detectedCoords?.lng ?? pickupWithCoords?.lng ?? -100.396631,
      latitudeDelta: 0.08,
      longitudeDelta: 0.08,
    };
  }

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
    <SafeAreaView style={styles.safe} edges={['top']}>
      <StatusBar barStyle="dark-content" backgroundColor="#FFF" />
      
      {/* HEADER ULTRA-SLIM CORREGIDO */}
      <View style={styles.topNav}>
        <TouchableOpacity 
          style={styles.locationSelector}
          onPress={() => openDeliveryFlow()}
          activeOpacity={0.7}
        >
          <Ionicons name={getOrderModeIcon()} size={18} color={Colors.primary} />
          <View style={styles.locationCopy}>
            <Text style={styles.locationLabel}>{getOrderModeLabel()}</Text>
            <View style={styles.row}>
              <Text style={styles.locationName} numberOfLines={1}>
                {branch?.nombre ?? 'Seleccionar sucursal'}
              </Text>
              <Ionicons name="swap-horizontal" size={14} color="#6B7280" />
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
          <View style={[styles.horizontalList, { paddingHorizontal: featuredInset }]}>
            {[1, 2, 3].map((i) => <Skeleton key={i} width={140} height={100} borderRadius={20} style={{marginRight: 12}} />)}
          </View>
        ) : (
          <FlatList
            horizontal
            data={categories}
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
          <View style={[styles.horizontalList, { paddingHorizontal: featuredInset }]}>
            {[1, 2].map((i) => <Skeleton key={i} width={featuredCardWidth} height={260} borderRadius={25} style={{marginRight: featuredGap}} />)}
          </View>
        ) : (
          <View>
            <Animated.FlatList
              horizontal
              data={featured}
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
              {featured?.map((_, i) => {
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
        )}

        {/* Espacio final interno para empujar el contenido arriba del dock */}
        <View style={styles.bottomSpacer} />
      </ScrollView>

      {/* BOTÓN FLOTANTE DE TIENDA */}
      <StoreFAB />

      {/* MODAL DE SELECCIÓN INICIAL */}
      <Modal visible={showTypeModal} transparent animationType="fade">
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Ionicons
              name={selectingPickupBranch ? 'storefront' : 'restaurant'}
              size={40}
              color={Colors.primary}
              style={{ marginBottom: 15 }}
            />
            <Text style={styles.modalTitle}>
              {selectingPickupBranch ? 'Elige donde recoger' : `Bienvenido a ${branch?.nombre || 'Amare'}`}
            </Text>
            <Text style={styles.modalSubtitle}>
              {selectingPickupBranch
                ? 'Selecciona una sucursal compatible con pickup.'
                : 'Como prefieres disfrutar tu comida hoy?'}
            </Text>
            
            {detectingLocation ? (
              <ActivityIndicator color={Colors.primary} style={{ marginVertical: 20 }} />
            ) : selectingPickupBranch ? (
              <View style={styles.pickupSelector}>
                {getPickupBranches().some((item) => item.lat != null && item.lng != null) && (
                  <MapView style={styles.pickupMap} initialRegion={getPickupMapRegion()}>
                    {getPickupBranches()
                      .filter((item) => item.lat != null && item.lng != null)
                      .map((item) => (
                        <Marker
                          key={item.id}
                          coordinate={{ latitude: item.lat!, longitude: item.lng! }}
                          title={item.nombre}
                          description={item.direccion || item.descripcion || 'Sucursal'}
                          onPress={() => selectBranchForType(item, 'pickup')}
                        />
                      ))}
                  </MapView>
                )}

                <ScrollView style={styles.pickupList} contentContainerStyle={styles.pickupListContent}>
                  {getPickupBranches().map((item) => (
                    <TouchableOpacity
                      key={item.id}
                      style={styles.pickupBranchButton}
                      onPress={() => selectBranchForType(item, 'pickup')}
                      activeOpacity={0.85}
                    >
                      <View style={styles.pickupBranchCopy}>
                        <Text style={styles.pickupBranchName}>{item.nombre}</Text>
                        <Text style={styles.pickupBranchAddress} numberOfLines={1}>
                          {item.direccion || item.descripcion || 'Sucursal'}
                        </Text>
                      </View>
                      {typeof item.distancia_km === 'number' && (
                        <Text style={styles.pickupBranchDistance}>{item.distancia_km.toFixed(1)} km</Text>
                      )}
                      <Ionicons name="chevron-forward" size={18} color="#6B7280" />
                    </TouchableOpacity>
                  ))}
                </ScrollView>

                <TouchableOpacity style={styles.modalBackButton} onPress={() => setSelectingPickupBranch(false)}>
                  <Ionicons name="arrow-back" size={16} color={Colors.primary} />
                  <Text style={styles.modalBackText}>Cambiar metodo</Text>
                </TouchableOpacity>
              </View>
            ) : (
              <View style={styles.selectorContainer}>
                <OrderTypeSelector 
                  value={tipoPedido as any} 
                  onChange={(tipo) => {
                    handleInitialTypeSelect(tipo);
                  }}
                  available={availableTypes}
                />
              </View>
            )}

            {availableTypes.length === 2 && !detectingLocation && !selectingPickupBranch && (
              <View style={styles.infoBox}>
                <Ionicons name="information-circle-outline" size={16} color="#6B7280" />
                <Text style={styles.infoText}>
                  La opción "En mesa" solo está disponible si te encuentras en el restaurante.
                </Text>
              </View>
            )}
          </View>
        </View>
      </Modal>
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
    paddingTop: 10,
    paddingBottom: 12,
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
    minWidth: 0,
    paddingVertical: 4, // Área de toque expandida
  },
  locationCopy: {
    flex: 1,
    minWidth: 0,
    marginLeft: 8,
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
    flexShrink: 1,
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
    paddingBottom: 176, 
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
  bottomSpacer: {
    height: 24,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.6)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modalContent: {
    backgroundColor: '#FFF',
    borderRadius: 30,
    padding: 25,
    width: '100%',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.25,
    shadowRadius: 15,
    elevation: 10,
  },
  modalTitle: {
    fontSize: 22,
    fontWeight: '800',
    color: '#111827',
    textAlign: 'center',
  },
  modalSubtitle: {
    fontSize: 15,
    color: '#6B7280',
    textAlign: 'center',
    marginTop: 8,
    marginBottom: 20,
  },
  selectorContainer: {
    width: '100%',
    marginVertical: 10,
  },
  pickupSelector: {
    width: '100%',
    gap: 12,
  },
  pickupMap: {
    width: '100%',
    height: 220,
    borderRadius: 18,
    overflow: 'hidden',
  },
  pickupList: {
    width: '100%',
    maxHeight: 190,
  },
  pickupListContent: {
    gap: 8,
  },
  pickupBranchButton: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 12,
    backgroundColor: '#F9FAFB',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    gap: 10,
  },
  pickupBranchCopy: {
    flex: 1,
    minWidth: 0,
  },
  pickupBranchName: {
    fontSize: 14,
    fontWeight: '700',
    color: '#111827',
  },
  pickupBranchAddress: {
    fontSize: 12,
    color: '#6B7280',
    marginTop: 2,
  },
  pickupBranchDistance: {
    fontSize: 12,
    fontWeight: '700',
    color: Colors.primary,
  },
  modalBackButton: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'center',
    gap: 6,
    paddingVertical: 8,
  },
  modalBackText: {
    fontSize: 13,
    fontWeight: '700',
    color: Colors.primary,
  },
  infoBox: {
    flexDirection: 'row',
    backgroundColor: '#F3F4F6',
    padding: 12,
    borderRadius: 12,
    marginTop: 20,
    alignItems: 'center',
    gap: 8,
  },
  infoText: {
    fontSize: 12,
    color: '#6B7280',
    flex: 1,
  },
});
