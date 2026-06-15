import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  TouchableOpacity,
  ScrollView,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { Image } from 'expo-image';
import { useRouter, useLocalSearchParams } from 'expo-router';
import MapView, { Region } from 'react-native-maps';
import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { apiClient, formatImageUrl, getApiError } from '../../services/api';
import { createPaymentIntent } from '../../services/orders.service';
import { Button } from '../../components/ui/Button';
import { Colors, Spacing, Shadows } from '../../theme';

export default function StoreCheckoutScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{
    productId: string;
    productName: string;
    productImage: string;
    productPrice: string;
    quantity: string;
    tipo_pedido: string;
  }>();

  const tipoPedido = (params.tipo_pedido ?? 'delivery') as 'delivery' | 'pickup';
  const productName = params.productName ?? 'Producto';
  const productImage = params.productImage ?? '';
  const productPrice = parseFloat(params.productPrice ?? '0');
  const quantity = parseInt(params.quantity ?? '1', 10);
  const subtotal = productPrice * quantity;
  const total = subtotal; // + envio if needed later

  const [direccionesGuardadas, setDireccionesGuardadas] = useState<any[]>([]);
  const [direccionSeleccionada, setDireccionSeleccionada] = useState<any | null>(null);
  const [showMap, setShowMap] = useState(false);
  const [addressData, setAddressData] = useState<any>(null);

  const [loading, setLoading] = useState(false);
  const [loadingLocation, setLoadingLocation] = useState(false);
  const [ubicacionVisual, setUbicacionVisual] = useState<string>('');
  const [coords, setCoords] = useState<{
    latitude: number;
    longitude: number;
    latitudeDelta: number;
    longitudeDelta: number;
  } | null>(null);

  useEffect(() => {
    cargarDirecciones();
  }, []);

  useEffect(() => {
    if (direccionesGuardadas.length === 0 && !loadingLocation && !ubicacionVisual) {
      setShowMap(true);
      obtenerUbicacionGPS();
    }
  }, [direccionesGuardadas]);

  async function cargarDirecciones() {
    try {
      const res = await apiClient.get('/profile/addresses');
      if (res.data.success || res.data.ok) {
        const dirs = Array.isArray(res.data.data) ? res.data.data : [];
        setDireccionesGuardadas(dirs);
        const principal = dirs.find((d: any) => d.es_principal) || dirs[0];
        if (principal) {
          setDireccionSeleccionada(principal);
          setUbicacionVisual(`${principal.calle} ${principal.numero || ''}, ${principal.colonia}`);
        }
      }
    } catch (err) {
      console.error('Error al cargar direcciones:', err);
    }
  }

  async function obtenerUbicacionGPS() {
    try {
      setLoadingLocation(true);
      const { status } = await Location.requestForegroundPermissionsAsync();

      if (status !== 'granted') {
        Alert.alert(
          'Permiso denegado',
          'Necesitamos acceso a tu ubicación para calcular el envío.'
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
          cp: address.postalCode,
        });
      }
    } catch {
      // ignore
    } finally {
      setLoadingLocation(false);
    }
  }

  function handleCambiarUbicacion() {
    Alert.alert(
      'Modificar dirección',
      'Introduce los detalles exactos de tu domicilio:',
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Guardar',
          onPress: () => {
            // simplified - use current text
          },
        },
      ]
    );
  }

  function handleRegionChangeComplete(region: Region) {
    setCoords(region);
    actualizarDireccionTexto(region.latitude, region.longitude);
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

  async function handleContinue() {
    if (!ubicacionVisual && !direccionSeleccionada) {
      Alert.alert('Dirección requerida', 'Por favor, introduce una dirección de entrega válida.');
      return;
    }

    setLoading(true);
    try {
      let finalAddressId = direccionSeleccionada?.id;

      if (!direccionSeleccionada && addressData) {
        finalAddressId = await promptToSaveAddress();
      }

      const { client_secret, id: intentId } = await createPaymentIntent({
        amount: total,
        currency: 'mxn',
      });

      router.push({
        pathname: '/checkout/payment-store' as any,
        params: {
          clientSecret: client_secret,
          intentId,
          productId: params.productId,
          productName,
          productImage,
          productPrice: params.productPrice,
          quantity: params.quantity,
          tipo_pedido: tipoPedido,
          direccionId: finalAddressId ? String(finalAddressId) : '',
          direccionEntrega: ubicacionVisual,
          total: String(total),
        },
      });
    } catch (err: any) {
      Alert.alert('Error', getApiError(err) || 'No se pudo iniciar el pago. Intenta de nuevo.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity style={styles.backButton} onPress={() => router.back()}>
          <Ionicons name="arrow-back" size={22} color={Colors.text} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Datos de entrega</Text>
        <View style={{ width: 40 }} />
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        {/* Delivery info banner */}
        <View style={styles.deliveryBanner}>
          <Ionicons name="bicycle" size={22} color={Colors.accent} />
          <View style={{ flex: 1, marginLeft: 10 }}>
            <Text style={styles.bannerTitle}>Envío a domicilio</Text>
            <Text style={styles.bannerSubtitle}>
              Este producto se entrega exclusivamente en tu domicilio
            </Text>
          </View>
        </View>

        {/* Product summary */}
        <View style={styles.productSummary}>
          <View style={styles.productImageContainer}>
            {productImage ? (
              <Image
                source={{ uri: formatImageUrl(productImage) ?? productImage }}
                style={styles.productImage}
                contentFit="cover"
              />
            ) : (
              <View style={styles.productImagePlaceholder}>
                <Ionicons name="cube-outline" size={22} color={Colors.muted} />
              </View>
            )}
          </View>
          <View style={styles.productDetails}>
            <Text style={styles.productName} numberOfLines={2}>{productName}</Text>
            <Text style={styles.productQty}>Cantidad: {quantity}</Text>
            <Text style={styles.productPrice}>${productPrice.toFixed(2)} c/u</Text>
          </View>
        </View>

        {/* Saved addresses */}
        {direccionesGuardadas.length > 0 && (
          <View style={styles.sectionBlock}>
            <Text style={styles.sectionTitle}>Tus direcciones guardadas</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginTop: 8 }}>
              {direccionesGuardadas.map((dir) => (
                <TouchableOpacity
                  key={dir.id}
                  style={[
                    styles.addressChip,
                    direccionSeleccionada?.id === dir.id && styles.addressChipActive,
                  ]}
                  onPress={() => {
                    setDireccionSeleccionada(dir);
                    setUbicacionVisual(`${dir.calle}, ${dir.colonia}`);
                    setShowMap(false);
                  }}
                >
                  <Ionicons
                    name="home-outline"
                    size={14}
                    color={direccionSeleccionada?.id === dir.id ? '#FFF' : '#6B7280'}
                  />
                  <Text
                    style={[
                      styles.addressChipText,
                      direccionSeleccionada?.id === dir.id && { color: '#FFF' },
                    ]}
                  >
                    {dir.alias || dir.calle}
                  </Text>
                </TouchableOpacity>
              ))}
              <TouchableOpacity
                style={styles.addressChip}
                onPress={() => {
                  setDireccionSeleccionada(null);
                  setShowMap(true);
                  obtenerUbicacionGPS();
                }}
              >
                <Ionicons name="add" size={16} color={Colors.accent} />
                <Text style={{ color: Colors.accent, fontWeight: '600' }}>Nueva</Text>
              </TouchableOpacity>
            </ScrollView>
          </View>
        )}

        {/* Location section */}
        <View style={styles.sectionBlock}>
          <View style={styles.locationHeader}>
            <Ionicons name="location" size={20} color={Colors.primary} />
            <Text style={styles.sectionTitle}>Dirección de entrega</Text>
          </View>

          <View style={styles.locationBox}>
            {showMap && coords && (
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
                  <Ionicons name="location" size={40} color={Colors.primary} style={{ marginTop: -40 }} />
                  <View style={styles.pointerShadow} />
                </View>
              </View>
            )}

            {loadingLocation ? (
              <View style={styles.locationLoading}>
                <ActivityIndicator size="small" color={Colors.primary} />
                <Text style={styles.locationTextMuted}>Detectando tu ubicación...</Text>
              </View>
            ) : (
              <>
                <Text style={styles.locationAddress} numberOfLines={2}>
                  {ubicacionVisual || 'Selecciona o introduce tu dirección'}
                </Text>

                <TouchableOpacity
                  style={styles.changeButton}
                  onPress={handleCambiarUbicacion}
                  activeOpacity={0.7}
                >
                  <Text style={styles.changeButtonText}>Cambiar dirección</Text>
                  <Ionicons name="create-outline" size={16} color={Colors.accent} />
                </TouchableOpacity>
              </>
            )}
          </View>
        </View>

        {/* Order summary */}
        <View style={styles.summaryContainer}>
          <Text style={styles.sectionTitle}>Resumen de compra</Text>
          <View style={styles.ticketBox}>
            <View style={styles.summaryRow}>
              <Text style={styles.summaryItem}>
                <Text style={styles.itemQuantity}>{quantity}x</Text>  {productName}
              </Text>
              <Text style={styles.summaryPrice}>${subtotal.toFixed(2)}</Text>
            </View>
            <View style={styles.divider} />
            <View style={styles.totalRow}>
              <Text style={styles.totalLabel}>Total a pagar</Text>
              <Text style={styles.totalValue}>${total.toFixed(2)} MXN</Text>
            </View>
          </View>
        </View>
      </ScrollView>

      {/* Bottom CTA */}
      <View style={styles.footer}>
        <Button
          label={`Continuar al pago • $${total.toFixed(2)}`}
          onPress={handleContinue}
          fullWidth
          size="lg"
          loading={loading}
          disabled={!ubicacionVisual || loadingLocation}
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
    paddingHorizontal: Spacing.base,
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
    letterSpacing: -0.3,
  },
  content: {
    padding: Spacing.base,
    paddingBottom: 140,
    gap: 20,
  },

  // Delivery banner
  deliveryBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFF7E6',
    borderRadius: 14,
    padding: 14,
    borderWidth: 1,
    borderColor: '#F5C060',
  },
  bannerTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: Colors.primary,
  },
  bannerSubtitle: {
    fontSize: 12,
    color: Colors.textSecondary,
    marginTop: 2,
  },

  // Product summary
  productSummary: {
    flexDirection: 'row',
    backgroundColor: '#F9FAFB',
    borderRadius: 14,
    padding: 12,
    borderWidth: 1,
    borderColor: '#F3F4F6',
    gap: 12,
  },
  productImageContainer: {
    width: 70,
    height: 70,
    borderRadius: 10,
    overflow: 'hidden',
    backgroundColor: '#E5E7EB',
  },
  productImage: {
    width: '100%',
    height: '100%',
  },
  productImagePlaceholder: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  productDetails: {
    flex: 1,
    justifyContent: 'center',
    gap: 3,
  },
  productName: {
    fontSize: 14,
    fontWeight: '700',
    color: Colors.text,
  },
  productQty: {
    fontSize: 13,
    color: Colors.textSecondary,
    fontWeight: '500',
  },
  productPrice: {
    fontSize: 13,
    fontWeight: '600',
    color: Colors.primary,
  },

  // Section
  sectionBlock: {
    gap: 8,
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: '#111827',
  },

  // Address chips
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
    backgroundColor: Colors.primary,
  },
  addressChipText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#4B5563',
  },

  // Location
  locationHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginLeft: 2,
    marginBottom: 4,
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
    color: Colors.accent,
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

  // Summary
  summaryContainer: {
    gap: 10,
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
    color: Colors.primary,
  },
  summaryPrice: {
    fontSize: 14,
    fontWeight: '600',
    color: '#111827',
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
  totalValue: { fontSize: 18, fontWeight: '800', color: Colors.primary },
  footer: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    paddingHorizontal: Spacing.base,
    paddingBottom: 32,
    paddingTop: 16,
    backgroundColor: '#FFFFFF',
    borderTopWidth: 1,
    borderTopColor: '#F3F4F6',
  },
});
