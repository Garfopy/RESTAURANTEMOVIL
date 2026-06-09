import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  TouchableOpacity,
  FlatList,
  ScrollView,
  Alert,
  ActivityIndicator,
  useWindowDimensions,
} from 'react-native';
import { useRouter } from 'expo-router';
import MapView, { Marker, Region } from 'react-native-maps';
import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { apiClient } from '../../services/api';
import { getRestaurantConfig } from '../../services/config.service';
import type { RestaurantConfig } from '@amare/types';

// Stores de la aplicación
import { useCartStore } from '../../store/cart.store';
import { useUserStore } from '../../store/user.store';
import { useBranchStore } from '../../store/branch.store'; // 🌟 Para leer los datos del restaurante

import { createPaymentIntent } from '../../services/orders.service';
import { Button } from '../../components/ui/Button';
import { Colors, Spacing, Shadows } from '../../theme';

const ALL_ORDER_TYPES = [
  {
    id: 'delivery',
    title: 'A domicilio',
    subtitle: 'Te lo llevamos rápido',
    icon: 'bicycle-outline',
    iconActive: 'bicycle',
  },
  {
    id: 'pickup',
    title: 'Para llevar',
    subtitle: 'Tú recoges en tienda',
    icon: 'bag-handle-outline',
    iconActive: 'bag-handle',
  },
  {
    id: 'eat_in',
    title: 'Comer aquí',
    subtitle: 'En el restaurante',
    icon: 'restaurant-outline',
    iconActive: 'restaurant',
  },
];

export default function OrderTypeScreen() {
  const router = useRouter();
  const { width: screenWidth } = useWindowDimensions();
  const { items, total, tipoPedido, setTipoPedido, restauranteId } = useCartStore();
  const { sucursales } = useBranchStore();
  
  const user = useUserStore(s => s.user);

  // Estados para direcciones persistentes
  const [direccionesGuardadas, setDireccionesGuardadas] = useState<any[]>([]);
  const [direccionSeleccionada, setDireccionSeleccionada] = useState<any | null>(null);
  const [showMap, setShowMap] = useState(false);
  const [addressData, setAddressData] = useState<any>(null); // Datos desglosados del geocode

  // Estados locales para el flujo de ubicación
  const [loading, setLoading] = useState(false);
  const [loadingLocation, setLoadingLocation] = useState(false);
  const [ubicacionVisual, setUbicacionVisual] = useState<string>('');
  const [coords, setCoords] = useState<{
    latitude: number;
    longitude: number;
    latitudeDelta: number;
    longitudeDelta: number;
  } | null>(null);

  // Configuración del restaurante (métodos de pago y tipos de entrega habilitados)
  const [config, setConfig] = useState<RestaurantConfig | null>(null);
  const [loadingConfig, setLoadingConfig] = useState(true);

  // Filtrar tipos de entrega según configuración
  const enabledOrderTypes = config
    ? ALL_ORDER_TYPES.filter((t) => config.tipos_entrega.includes(t.id as never))
    : ALL_ORDER_TYPES.filter((t) => t.id !== 'eat_in'); // Por defecto: delivery + pickup

  // Calcular dimensiones dinámicas de cards según cantidad
  function getCardDimensions() {
    const count = enabledOrderTypes.length;
    const containerPadding = (Spacing.base || 16) * 2;
    const gap = 16;
    const availableWidth = screenWidth - containerPadding;

    if (count === 1) {
      return {
        width: Math.min(availableWidth * 0.55, 200),
        aspectRatio: 1.1,
        iconSize: 36,
        cardPadding: 16,
        justifyContent: 'center' as const,
      };
    }
    if (count === 2) {
      return {
        width: (availableWidth - gap) / 2,
        aspectRatio: 0.9,
        iconSize: 32,
        cardPadding: 14,
        justifyContent: 'space-between' as const,
      };
    }
    // 3 métodos
    return {
      width: (availableWidth - gap * 2) / 3,
      aspectRatio: 0.8,
      iconSize: 28,
      cardPadding: 12,
      justifyContent: 'space-between' as const,
    };
  }

  // Sincronizar y procesar la ubicación según el tipo de entrega seleccionado
  useEffect(() => {
    cargarDirecciones();
    cargarConfiguracion();
  }, []);

  useEffect(() => {
    if (!tipoPedido) return;

    if (tipoPedido === 'delivery') {
      // Lógica para entrega a domicilio
      if (direccionesGuardadas.length === 0) {
        setShowMap(true);
        obtenerUbicacionGPS();
      }
    } else if (tipoPedido === 'pickup') {
      // Lógica para recoger en tienda
      setShowMap(false);
      const sucursalIdStr = String(restauranteId);
      const sucursal = sucursales.find(s => String(s.id) === sucursalIdStr);
      setUbicacionVisual(sucursal?.direccion || sucursal?.descripcion || 'Sucursal Seleccionada');
    } else if (tipoPedido === 'eat_in') {
      // Comer en el restaurante
      setShowMap(false);
      const sucursalIdStr = String(restauranteId);
      const sucursal = sucursales.find(s => String(s.id) === sucursalIdStr);
      setUbicacionVisual(sucursal?.nombre || 'Restaurante');
    }
  }, [tipoPedido, restauranteId, sucursales]);

  async function cargarConfiguracion() {
    if (!restauranteId) {
      setLoadingConfig(false);
      return;
    }
    try {
      const cfg = await getRestaurantConfig(restauranteId);
      setConfig(cfg);
      // Si el tipo actual no está habilitado, resetear al primero disponible
      if (tipoPedido && !cfg.tipos_entrega.includes(tipoPedido as never)) {
        setTipoPedido(cfg.tipos_entrega[0] as never);
      }
    } catch (err) {
      console.error('Error al cargar configuración del restaurante:', err);
    } finally {
      setLoadingConfig(false);
    }
  }

  async function cargarDirecciones() {
    try {
      const res = await apiClient.get('/profile/addresses');
      if (res.data.success || res.data.ok) {
        setDireccionesGuardadas(Array.isArray(res.data.data) ? res.data.data : []);
        const principal = res.data.data.find((d: any) => d.es_principal) || res.data.data[0];
        if (principal) {
          setDireccionSeleccionada(principal);
          setUbicacionVisual(`${principal.calle} ${principal.numero || ''}, ${principal.colonia}`);
        }
      }
    } catch (err) {
      console.error('Error al cargar direcciones:', err);
    }
  }

  // Función asíncrona para consultar el GPS del dispositivo y aplicar geocoding inverso
  async function obtenerUbicacionGPS() {
    try {
      setLoadingLocation(true);
      const { status } = await Location.requestForegroundPermissionsAsync();
      
      if (status !== 'granted') {
        Alert.alert(
          'Permiso denegado',
          'Necesitamos acceso a tu ubicación para calcular el envío. Puedes escribir tu dirección manualmente.'
        );
        setUbicacionVisual('Escribe tu dirección aquí...');
        return;
      }

      const location = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced,
      });

      const initialRegion = {
        latitude: location.coords.latitude,
        longitude: location.coords.longitude,
        latitudeDelta: 0.005,
        longitudeDelta: 0.005,
      };

      setCoords(initialRegion);
      await actualizarDireccionTexto(initialRegion.latitude, initialRegion.longitude);
      
    } catch (error) {
      console.error('Error al obtener la ubicación:', error);
      setUbicacionVisual('Dirección no encontrada automáticamente');
    } finally {
      setLoadingLocation(false);
    }
  }

  // Nueva función para actualizar el texto de la dirección basado en coordenadas
  async function actualizarDireccionTexto(lat: number, lng: number) {
    try {
      const reverseGeocode = await Location.reverseGeocodeAsync({
        latitude: lat,
        longitude: lng,
      });

      if (reverseGeocode && reverseGeocode.length > 0) {
        const address = reverseGeocode[0];
        const calle = `${address.street || 'Calle'} ${address.name || ''}`;
        const colonia = address.district || address.subregion || '';
        const direccionFormateada = `${calle}, Col. ${colonia}, ${address.city || ''}`;
        
        setUbicacionVisual(direccionFormateada);
        setAddressData({
          calle,
          colonia,
          ciudad: address.city || '',
          lat,
          lng,
          cp: address.postalCode
        });
      }
    } finally {
      setLoadingLocation(false);
    }
  }

  // Permite al usuario re-escribir o seleccionar otra dirección si lo desea
  function handleCambiarUbicacion() {
    if (tipoPedido === 'delivery') {
      Alert.prompt(
        'Modificar dirección',
        'Introduce los detalles exactos de tu domicilio:',
        [
          { text: 'Cancelar', style: 'cancel' },
          {
            text: 'Guardar',
            onPress: (nuevaDireccion: string | undefined) => {
              if (nuevaDireccion && nuevaDireccion.trim() !== '') {
                setUbicacionVisual(nuevaDireccion);
                setAddressData((prev: any) => prev ? { ...prev, calle: nuevaDireccion, colonia: '' } : { calle: nuevaDireccion, colonia: '', ciudad: '', lat: 0, lng: 0, cp: '' });
              }
            },
          },
        ],
        'plain-text',
        ubicacionVisual
      );
    }
  }

  // Se dispara cuando el usuario termina de mover el mapa
  function handleRegionChangeComplete(region: Region) {
    if (tipoPedido === 'delivery') {
      setCoords(region);
      // Solo actualizamos el texto si no estamos cargando inicialmente
      actualizarDireccionTexto(region.latitude, region.longitude);
    }
  }

  async function promptToSaveAddress(): Promise<string | null> {
    return new Promise((resolve) => {
      Alert.alert(
        '¿Guardar esta dirección?',
        'Podrás usarla en futuros pedidos a domicilio',
        [
          {
            text: 'No guardar',
            onPress: () => resolve(null),
            style: 'cancel',
          },
          {
            text: 'Guardar',
            onPress: async () => {
              // Mostrar opciones de alias
              Alert.alert(
                'Tipo de dirección',
                'Elige cómo quieres llamar esta dirección',
                [
                  {
                    text: 'Casa',
                    onPress: () => saveAddressWithAlias('Casa'),
                  },
                  {
                    text: 'Trabajo',
                    onPress: () => saveAddressWithAlias('Trabajo'),
                  },
                  {
                    text: 'Otro',
                    onPress: () => {
                      // Por ahora, usar "Otro"
                      saveAddressWithAlias('Otro');
                    },
                  },
                  {
                    text: 'Cancelar',
                    style: 'cancel',
                    onPress: () => resolve(null),
                  },
                ]
              );
            },
          },
        ]
      );

      async function saveAddressWithAlias(alias: string) {
        if (!addressData) {
          resolve(null);
          return;
        }
        try {
          const res = await apiClient.post('/profile/addresses', {
            alias,
            calle: addressData.calle,
            colonia: addressData.colonia,
            ciudad: addressData.ciudad,
            lat: addressData.lat,
            lng: addressData.lng,
            cp: addressData.cp,
            es_principal: direccionesGuardadas.length === 0,
          });
          resolve(res.data.data?.id || null);
        } catch (err) {
          console.error('Error al guardar dirección:', err);
          resolve(null);
        }
      }
    });
  }

  const cardDims = getCardDimensions();
  const count = enabledOrderTypes.length;

  async function handleContinue() {
    if (!tipoPedido) {
      Alert.alert('Selección requerida', 'Por favor, elige cómo quieres recibir tu pedido.');
      return;
    }

    if (tipoPedido === 'delivery' && !direccionSeleccionada && !addressData) {
      Alert.alert('Dirección requerida', 'Por favor, introduce una dirección de entrega válida.');
      return;
    }

    setLoading(true);
    try {
      let finalAddressId = direccionSeleccionada?.id;
      
      // Si el usuario movió el mapa y no seleccionó una guardada, preguntar si la quiere guardar
      if (tipoPedido === 'delivery' && !direccionSeleccionada && addressData) {
        finalAddressId = await promptToSaveAddress();
      }

      const { client_secret, id: intentId } = await createPaymentIntent({
        order_id: restauranteId!,
        amount: total,
        currency: 'mxn',
      });

      router.push({
        pathname: '/checkout/payment',
        params: {
          clientSecret: client_secret,
          intentId,
          restauranteId: String(restauranteId),
          tipoPedido,
          direccionId: finalAddressId ? String(finalAddressId) : '',
          direccionEntrega: ubicacionVisual,
        },
      });
    } catch (err) {
      Alert.alert('Error', 'No se pudo iniciar el pago. Intenta de nuevo.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      {/* HEADER */}
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backButton}
          onPress={() => router.back()}
          accessibilityLabel="Volver atrás"
          accessibilityRole="button"
          testID="back-btn"
        >
          <Ionicons name="arrow-back" size={22} color={Colors.text || '#111827'} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Método de Entrega</Text>
        <View style={{ width: 40 }} />
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.welcomeSection}>
          <Text style={styles.mainLabel}>¿Cómo quieres recibir tu pedido?</Text>
          <Text style={styles.subLabel}>Elige la opción que mejor se adapte a ti hoy.</Text>
        </View>

        {/* CARDS SELECCIÓN */}
        <View style={[styles.cardsContainer, count === 1 && styles.cardsContainerCentered]}>
          {enabledOrderTypes.map((type) => {
            const isSelected = tipoPedido === type.id;
            const iconWrapperSize = cardDims.iconSize * 1.7; // ~61px para 1 card, ~54px para 2-3
            return (
              <TouchableOpacity
                key={type.id}
                activeOpacity={0.8}
                style={[
                  styles.squareCard,
                  {
                    width: cardDims.width,
                    aspectRatio: cardDims.aspectRatio,
                    padding: cardDims.cardPadding,
                  },
                  isSelected && styles.squareCardActive,
                ]}
                onPress={() => setTipoPedido(type.id as never)}                accessibilityLabel={`Seleccionar ${type.title}`}
                accessibilityRole="radio"
                accessibilityState={{ selected: isSelected }}
                testID={`order-type-${type.id}`}              >
                <View
                  style={[
                    styles.iconWrapper,
                    {
                      width: iconWrapperSize,
                      height: iconWrapperSize,
                      borderRadius: iconWrapperSize / 2,
                    },
                    isSelected && styles.iconWrapperActive,
                  ]}
                >
                  <Ionicons
                    name={(isSelected ? type.iconActive : type.icon) as never}
                    size={cardDims.iconSize}
                    color={isSelected ? (Colors.primary || '#111827') : '#6B7280'}
                  />
                </View>
                <Text style={[styles.cardTitle, isSelected && styles.cardTitleActive]}>
                  {type.title}
                </Text>
                <Text style={styles.cardSubtitle}>{type.subtitle}</Text>
              </TouchableOpacity>
            );
          })}
        </View>

        {/* 🌟 SECCIÓN DINÁMICA DE DETALLES DE UBICACIÓN */}
        {tipoPedido ? (
          <View style={styles.locationContainer}>
            {tipoPedido === 'delivery' && direccionesGuardadas.length > 0 && (
              <View style={{ marginBottom: 10 }}>
                <Text style={styles.locationTitle}>Tus direcciones</Text>
                <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginTop: 8 }}>
                  {direccionesGuardadas.map((dir) => (
                    <TouchableOpacity
                      key={dir.id}
                      style={[styles.addressChip, direccionSeleccionada?.id === dir.id && styles.addressChipActive]}
                      onPress={() => {
                        setDireccionSeleccionada(dir);
                        setUbicacionVisual(`${dir.calle}, ${dir.colonia}`);
                        setShowMap(false);
                      }}
                      accessibilityLabel={`Seleccionar dirección: ${dir.alias}`}
                      accessibilityRole="radio"
                      accessibilityState={{ selected: direccionSeleccionada?.id === dir.id }}
                      testID={`address-chip-${dir.id}`}
                    >
                      <Ionicons name="home-outline" size={14} color={direccionSeleccionada?.id === dir.id ? '#FFF' : '#6B7280'} />
                      <Text style={[styles.addressChipText, direccionSeleccionada?.id === dir.id && { color: '#FFF' }]}>{dir.alias}</Text>
                    </TouchableOpacity>
                  ))}
                  <TouchableOpacity 
                    style={styles.addressChip} 
                    onPress={() => {
                      setDireccionSeleccionada(null);
                      setShowMap(true);
                      obtenerUbicacionGPS();
                    }}
                    accessibilityLabel="Agregar nueva dirección"
                    accessibilityRole="button"
                    testID="add-address-btn"
                  >
                    <Ionicons name="add" size={16} color={Colors.primary} />
                    <Text style={{ color: Colors.primary, fontWeight: '600' }}>Nueva</Text>
                  </TouchableOpacity>
                </ScrollView>
              </View>
            )}

            {(!direccionesGuardadas.length || tipoPedido === 'pickup' || showMap) && (
              <View style={styles.locationHeader}>
                <Ionicons 
                  name={tipoPedido === 'delivery' ? "location" : "storefront"} 
                  size={20} 
                  color={Colors.primary || '#111827'} 
                />
                <Text style={styles.locationTitle}>
                  {tipoPedido === 'delivery' ? 'Dirección de Entrega' : 'Recoges en'}
                </Text>
              </View>
            )}
            
            <View style={styles.locationBox}>
              {tipoPedido === 'delivery' && coords && (showMap || direccionesGuardadas.length === 0) && (
                <View style={styles.mapWrapper}>
                  <MapView
                    style={styles.miniMap}
                    region={coords}
                    onRegionChangeComplete={handleRegionChangeComplete}
                    showsUserLocation
                    rotateEnabled={false}
                    pitchEnabled={false}
                  />
                  <View style={styles.mapCenterPointer} pointerEvents="none">
                    <Ionicons name="location" size={40} color={Colors.primary || '#111827'} style={{ marginTop: -40 }} />
                    <View style={styles.pointerShadow} />
                  </View>
                </View>
              )}

              {loadingLocation ? (
                <View style={styles.locationLoading}>
                  <ActivityIndicator size="small" color={Colors.primary || '#111827'} />
                  <Text style={styles.locationTextMuted}>Detectando tu ubicación...</Text>
                </View>
              ) : (
                <>
                  <Text style={styles.locationAddress} numberOfLines={2}>
                    {ubicacionVisual}
                  </Text>
                  
                  {tipoPedido === 'delivery' && (
                    <TouchableOpacity 
                      style={styles.changeButton} 
                      onPress={handleCambiarUbicacion}
                      activeOpacity={0.7}
                      accessibilityLabel="Cambiar dirección de entrega"
                      accessibilityRole="button"
                      accessibilityHint="Permite editar o seleccionar una dirección diferente"
                      testID="change-address-btn"
                    >
                      <Text style={styles.changeButtonText}>Cambiar dirección</Text>
                      <Ionicons name="create-outline" size={16} color={Colors.primary || '#111827'} />
                    </TouchableOpacity>
                  )}
                </>
              )}
            </View>
          </View>
        ) : null}

        {/* RESUMEN DE COMPRA */}
        <View style={styles.summaryContainer}>
          <Text style={styles.summaryTitle}>Resumen del Pedido</Text>
          <View style={styles.ticketBox}>
            {items.map((item) => (
              <View key={item.id} style={styles.summaryRow}>
                <Text style={styles.summaryItem} numberOfLines={1}>
                  <Text style={styles.itemQuantity}>{item.cantidad}x</Text>    {item.platillo.nombre}
                </Text>
                <Text style={styles.summaryPrice}>${item.subtotal.toFixed(2)}</Text>
              </View>
            ))}
            <View style={styles.divider} />
            <View style={styles.totalRow}>
              <Text style={styles.totalLabel}>Total a pagar</Text>
              <Text style={styles.totalValue}>${total.toFixed(2)} MXN</Text>
            </View>
          </View>
        </View>
      </ScrollView>

      {/* FOOTER FIJO */}
      <View style={styles.footer}>
        <Button
          label="Continuar al pago"
          onPress={handleContinue}
          fullWidth
          size="lg"
          loading={loading}
          disabled={!tipoPedido || loadingLocation}
          style={styles.actionButton}
          accessibilityLabel="Continuar al pago"
          accessibilityHint="Proceder al método de pago"
          testID="checkout-continue-btn"
        />
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#FFFFFF' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.base || 16,
    paddingVertical: 12,
  },
  backButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  headerTitle: { 
    fontSize: 18, 
    fontWeight: '700', 
    color: '#111827',
    letterSpacing: -0.3 
  },
  content: { 
    padding: Spacing.base || 16, 
    paddingBottom: 140, 
    gap: 24 
  },
  welcomeSection: {
    gap: 4,
    marginTop: 8,
  },
  mainLabel: { 
    fontSize: 24, 
    fontWeight: '800', 
    color: '#111827',
    letterSpacing: -0.5
  },
  subLabel: {
    fontSize: 15,
    color: '#6B7280',
  },
  cardsContainer: {
    flexDirection: 'row',
    gap: 16,
    width: '100%',
  },
  cardsContainerCentered: {
    justifyContent: 'center',
  },
  squareCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 2,
    borderColor: '#E5E7EB',
    ...Shadows.sm,
    shadowColor: '#000',
    shadowOpacity: 0.04,
    shadowRadius: 12,
  },
  squareCardActive: {
    borderColor: Colors.primary || '#111827',
    backgroundColor: '#FAFAFA',
    ...Shadows.md,
    shadowOpacity: 0.08,
  },
  iconWrapper: {
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  iconWrapperActive: {
    backgroundColor: '#F3F4F6',
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#4B5563',
    marginBottom: 4,
  },
  cardTitleActive: {
    color: Colors.primary || '#111827',
  },
  cardSubtitle: {
    fontSize: 12,
    color: '#9CA3AF',
    textAlign: 'center',
  },
  
  // ESTILOS DE LA SECCIÓN DE UBICACIÓN DINÁMICA
  locationContainer: {
    gap: 10,
    backgroundColor: '#FFFFFF',
  },
  locationHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginLeft: 4,
  },
  locationTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
  },
  addressChip: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: '#F3F4F6',
    marginRight: 8,
    gap: 6,
  },
  addressChipActive: {
    backgroundColor: Colors.primary || '#111827',
  },
  addressChipText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#4B5563',
  },
  locationBox: {
    backgroundColor: '#F9FAFB',
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: '#F3F4F6',
    gap: 12,
  },
  locationAddress: {
    fontSize: 14,
    color: '#374151',
    fontWeight: '500',
    lineHeight: 20,
  },
  locationLoading: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 4,
  },
  locationTextMuted: {
    fontSize: 14,
    color: '#6B7280',
  },
  changeButton: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    gap: 4,
    paddingVertical: 4,
  },
  changeButtonText: {
    fontSize: 13,
    fontWeight: '600',
    color: Colors.primary || '#111827',
  },
  mapWrapper: {
    height: 180,
    width: '100%',
    borderRadius: 12,
    overflow: 'hidden',
    marginBottom: 8,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  miniMap: {
    flex: 1,
  },
  mapCenterPointer: {
    position: 'absolute',
    top: '50%',
    left: '50%',
    marginLeft: -20,
    marginTop: -20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  pointerShadow: {
    width: 4,
    height: 4,
    borderRadius: 2,
    backgroundColor: 'rgba(0,0,0,0.2)',
    marginTop: -2,
  },

  // SUMMARY
  summaryContainer: {
    gap: 12,
  },
  summaryTitle: { 
    fontSize: 16, 
    fontWeight: '700', 
    color: '#111827',
    marginLeft: 4,
  },
  ticketBox: {
    backgroundColor: '#F9FAFB',
    borderRadius: 16,
    padding: 18,
    borderWidth: 1,
    borderColor: '#F3F4F6',
  },
  summaryRow: { 
    flexDirection: 'row', 
    justifyContent: 'space-between', 
    alignItems: 'center',
    paddingVertical: 6,
  },
  summaryItem: { 
    flex: 1, 
    fontSize: 14, 
    color: '#4B5563',
    fontWeight: '500',
  },
  itemQuantity: {
    fontWeight: '700',
    color: Colors.primary || '#111827',
  },
  summaryPrice: { 
    fontSize: 14, 
    fontWeight: '600', 
    color: '#111827' 
  },
  divider: { 
    height: 1, 
    backgroundColor: '#E5E7EB', 
    marginVertical: 12,
    borderStyle: 'dashed',
  },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 4,
  },
  totalLabel: { fontSize: 15, fontWeight: '700', color: '#111827' },
  totalValue: { fontSize: 18, fontWeight: '800', color: Colors.primary || '#111827' },
  footer: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    paddingHorizontal: Spacing.base || 16,
    paddingBottom: 32,
    paddingTop: 16,
    backgroundColor: '#FFFFFF',
    borderTopWidth: 1,
    borderTopColor: '#F3F4F6',
  },
  actionButton: {
    borderRadius: 14,
  }
});