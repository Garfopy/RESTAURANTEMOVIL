import React, { useEffect, useMemo, useState } from 'react';
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
import { useRouter } from 'expo-router';
import MapView, { Region } from 'react-native-maps';
import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { apiClient, getApiError } from '../../services/api';
import { useCartStore } from '../../store/cart.store';
import { useBranchStore } from '../../store/branch.store';
import { useTableSessionStore } from '../../store/table-session.store';
import { createOrder, createPaymentIntent } from '../../services/orders.service';
import { Button } from '../../components/ui/Button';
import { Colors, Spacing } from '../../theme';

type SavedAddress = {
  id: number | string;
  alias?: string;
  calle?: string;
  numero?: string;
  colonia?: string;
  ciudad?: string;
  cp?: string | null;
  lat?: number | string | null;
  lng?: number | string | null;
  instrucciones?: string | null;
  es_principal?: boolean;
};

type AddressData = {
  calle: string;
  colonia: string;
  ciudad: string;
  lat: number;
  lng: number;
  cp?: string | null;
};

function getSelectedExtras(item: ReturnType<typeof useCartStore.getState>['items'][number]) {
  return item.modificadores_seleccionados.flatMap((mod) =>
    mod.opciones.map((opcion) => ({
      key: `${mod.modificador_id}-${opcion.opcion_id}`,
      nombre: opcion.opcion_nombre,
      precio: Number(opcion.precio_extra || 0),
    }))
  );
}

function getItemCostBreakdown(item: ReturnType<typeof useCartStore.getState>['items'][number]) {
  const selectedExtras = getSelectedExtras(item);
  const baseUnit = Number(item.platillo.precio || 0);
  const extrasUnit = selectedExtras.reduce((sum, extra) => sum + extra.precio, 0);
  const unitTotal = baseUnit + extrasUnit;
  const lineTotal = unitTotal * item.cantidad;

  return {
    selectedExtras,
    baseUnit,
    unitTotal,
    lineTotal,
  };
}

export default function OrderTypeScreen() {
  const router = useRouter();
  const { items, tipoPedido, restauranteId, deliveryAddress, setDeliveryAddress, clear } = useCartStore();
  const { sucursales, seleccionada } = useBranchStore();
  const tableSession = useTableSessionStore((s) => s.session);
  const orderTotal = useMemo(
    () => items.reduce((sum, item) => sum + getItemCostBreakdown(item).lineTotal, 0),
    [items]
  );

  const resolvedRestaurantId =
    restauranteId ??
    seleccionada?.id ??
    items[0]?.platillo?.restaurante_id ??
    null;

  const selectedBranch = useMemo(
    () => sucursales.find((s) => String(s.id) === String(resolvedRestaurantId)) ?? seleccionada ?? null,
    [resolvedRestaurantId, seleccionada, sucursales]
  );

  const [direccionesGuardadas, setDireccionesGuardadas] = useState<SavedAddress[]>([]);
  const [direccionSeleccionada, setDireccionSeleccionada] = useState<SavedAddress | null>(null);
  const [showMap, setShowMap] = useState(false);
  const [addressData, setAddressData] = useState<AddressData | null>(null);
  const [loading, setLoading] = useState(false);
  const [loadingLocation, setLoadingLocation] = useState(false);
  const [ubicacionVisual, setUbicacionVisual] = useState('');
  const [coords, setCoords] = useState<Region | null>(null);

  useEffect(() => {
    cargarDirecciones();
  }, []);

  useEffect(() => {
    if (tipoPedido !== 'delivery' || !deliveryAddress || direccionSeleccionada || addressData) {
      return;
    }

    const storedAddress: SavedAddress = {
      id: deliveryAddress.id ?? 'delivery-selected',
      alias: deliveryAddress.alias ?? 'Direccion',
      calle: deliveryAddress.text,
      ciudad: '',
      lat: deliveryAddress.lat ?? null,
      lng: deliveryAddress.lng ?? null,
      instrucciones: deliveryAddress.instrucciones ?? null,
    };

    setDireccionSeleccionada(storedAddress);
    setUbicacionVisual(deliveryAddress.text);

    if (Number.isFinite(Number(deliveryAddress.lat)) && Number.isFinite(Number(deliveryAddress.lng))) {
      setCoords({
        latitude: Number(deliveryAddress.lat),
        longitude: Number(deliveryAddress.lng),
        latitudeDelta: 0.005,
        longitudeDelta: 0.005,
      });
    }
  }, [addressData, deliveryAddress, direccionSeleccionada, tipoPedido]);

  useEffect(() => {
    if (!tipoPedido) {
      setUbicacionVisual('');
      return;
    }

    if (tipoPedido === 'delivery') {
      if (direccionSeleccionada) {
        setUbicacionVisual(formatAddress(direccionSeleccionada));
        setShowMap(false);
        return;
      }

      if (!addressData && !coords && !loadingLocation) {
        setShowMap(true);
        obtenerUbicacionGPS();
      }
      return;
    }

    setShowMap(false);
    if (tipoPedido === 'pickup') {
      setUbicacionVisual(
        selectedBranch
          ? `${selectedBranch.nombre} · ${selectedBranch.direccion || selectedBranch.descripcion || 'Sucursal'}`
          : 'Sucursal seleccionada'
      );
      return;
    }

    setUbicacionVisual(
      tableSession
        ? `${selectedBranch?.nombre || 'Sucursal'} · ${tableSession.mesaLabel}`
        : selectedBranch?.nombre || 'Comer aqui'
    );
  }, [addressData, coords, direccionSeleccionada, loadingLocation, selectedBranch, tableSession, tipoPedido]);

  async function cargarDirecciones() {
    try {
      const res = await apiClient.get('/profile/addresses');
      if (res.data.success || res.data.ok) {
        const addresses = Array.isArray(res.data.data) ? res.data.data : [];
        setDireccionesGuardadas(addresses);
        const stored =
          deliveryAddress?.id != null
            ? addresses.find((d: SavedAddress) => String(d.id) === String(deliveryAddress.id))
            : null;
        const principal = stored || addresses.find((d: SavedAddress) => d.es_principal) || addresses[0];
        if (principal) {
          setDireccionSeleccionada(principal);
          setUbicacionVisual(formatAddress(principal));
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
          'Necesitamos acceso a tu ubicacion para calcular el envio. Puedes escribir tu direccion manualmente.'
        );
        setUbicacionVisual('Escribe tu direccion aqui...');
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
      console.error('Error al obtener la ubicacion:', error);
      setUbicacionVisual('Direccion no encontrada automaticamente');
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
        const calle = `${address.street || 'Calle'} ${address.name || ''}`.trim();
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
    } finally {
      setLoadingLocation(false);
    }
  }

  function handleCambiarUbicacion() {
    if (tipoPedido !== 'delivery') return;

    Alert.prompt(
      'Modificar direccion',
      'Introduce los detalles exactos de tu domicilio:',
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Guardar',
          onPress: (nuevaDireccion: string | undefined) => {
            if (nuevaDireccion && nuevaDireccion.trim() !== '') {
              setDireccionSeleccionada(null);
              setDeliveryAddress(null);
              setShowMap(false);
              setUbicacionVisual(nuevaDireccion);
              setAddressData((prev) =>
                prev
                  ? { ...prev, calle: nuevaDireccion, colonia: '' }
                  : { calle: nuevaDireccion, colonia: '', ciudad: '', lat: 0, lng: 0, cp: '' }
              );
            }
          },
        },
      ],
      'plain-text',
      ubicacionVisual
    );
  }

  function handleRegionChangeComplete(region: Region) {
    if (tipoPedido === 'delivery') {
      setDireccionSeleccionada(null);
      setDeliveryAddress(null);
      setCoords(region);
      actualizarDireccionTexto(region.latitude, region.longitude);
    }
  }

  async function promptToSaveAddress(): Promise<string | number | null> {
    return new Promise((resolve) => {
      Alert.alert(
        'Guardar esta direccion?',
        'Podras usarla en futuros pedidos a domicilio',
        [
          {
            text: 'No guardar',
            onPress: () => resolve(null),
            style: 'cancel',
          },
          {
            text: 'Guardar',
            onPress: () => {
              Alert.alert(
                'Tipo de direccion',
                'Elige como quieres llamar esta direccion',
                [
                  { text: 'Casa', onPress: () => saveAddressWithAlias('Casa') },
                  { text: 'Trabajo', onPress: () => saveAddressWithAlias('Trabajo') },
                  { text: 'Otro', onPress: () => saveAddressWithAlias('Otro') },
                  { text: 'Cancelar', style: 'cancel', onPress: () => resolve(null) },
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
          const saved = res.data.data;
          if (saved) {
            setDeliveryAddress(addressToSelection(saved));
          }
          resolve(saved?.id || null);
        } catch (err) {
          console.error('Error al guardar direccion:', err);
          resolve(null);
        }
      }
    });
  }

  async function handleContinue() {
    if (!tipoPedido) {
      Alert.alert('Seleccion requerida', 'Vuelve al inicio y elige como quieres recibir tu pedido.');
      return;
    }

    if (tipoPedido === 'delivery' && !direccionSeleccionada && !addressData) {
      Alert.alert('Direccion requerida', 'Por favor, introduce una direccion de entrega valida.');
      return;
    }

    if (tipoPedido === 'eat_in' && !tableSession) {
      Alert.alert('Mesa requerida', 'Escanea el QR de tu mesa para poder pedir.', [
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Escanear QR', onPress: () => router.push({ pathname: '/table-scanner', params: { returnTo: '/checkout/order-type' } }) },
      ]);
      return;
    }

    if (tipoPedido === 'eat_in' && tableSession && Number(resolvedRestaurantId) !== tableSession.restauranteId) {
      Alert.alert(
        'Mesa de otra sucursal',
        'La mesa escaneada pertenece a otra sucursal. Escanea el QR de esta mesa o vacia el carrito para cambiar de sucursal.',
        [
          { text: 'Cancelar', style: 'cancel' },
          { text: 'Escanear QR', onPress: () => router.push({ pathname: '/table-scanner', params: { returnTo: '/checkout/order-type' } }) },
        ]
      );
      return;
    }

    if (!resolvedRestaurantId || Number.isNaN(Number(resolvedRestaurantId))) {
      Alert.alert('Error', 'No se detecto la sucursal del pedido. Vuelve al menu y selecciona una sucursal antes de pagar.');
      return;
    }

    setLoading(true);
    try {
      if (tipoPedido === 'eat_in') {
        const order = await createOrder({
          restaurante_id: Number(resolvedRestaurantId),
          tipo_pedido: 'eat_in',
          mesa_id: tableSession?.mesaId,
          direccion_entrega: ubicacionVisual,
          items: items.map((i) => ({
            platillo_id: i.platillo.id,
            cantidad: i.cantidad,
            precio_unit: getItemCostBreakdown(i).unitTotal,
            notas: i.notas,
            modificadores: i.modificadores_seleccionados.map((m) => ({
              modificador_id: m.modificador_id,
              modificador_nombre: m.modificador_nombre,
              opciones: m.opciones.map((o) => ({
                opcion_id: o.opcion_id,
                opcion_nombre: o.opcion_nombre,
                precio_extra: o.precio_extra,
              })),
            })),
          })),
          notas: tableSession ? `Cuenta abierta · ${tableSession.mesaLabel}` : 'Cuenta abierta',
        });

        clear();
        router.replace({ pathname: '/order/[id]', params: { id: String(order.id) } });
        return;
      }

      let finalAddressId: string | number | undefined = direccionSeleccionada?.id;

      if (tipoPedido === 'delivery' && !direccionSeleccionada && addressData) {
        finalAddressId = (await promptToSaveAddress()) ?? undefined;
      }

      const { client_secret, id: intentId } = await createPaymentIntent({
        amount: orderTotal,
        currency: 'mxn',
      });

      router.push({
        pathname: '/checkout/payment',
        params: {
          clientSecret: client_secret,
          intentId,
          restauranteId: String(resolvedRestaurantId),
          tipoPedido,
          direccionId: finalAddressId ? String(finalAddressId) : '',
          direccionEntrega: ubicacionVisual,
          mesaId: tableSession ? String(tableSession.mesaId) : '',
          mesaLabel: tableSession?.mesaLabel ?? '',
        },
      });
    } catch (err) {
      Alert.alert('Error', getApiError(err) || 'No se pudo iniciar el pago. Intenta de nuevo.');
    } finally {
      setLoading(false);
    }
  }

  function formatAddress(address: SavedAddress) {
    const street = [address.calle, address.numero].filter(Boolean).join(' ');
    return [street, address.colonia, address.ciudad].filter(Boolean).join(', ');
  }

  function addressToSelection(address: SavedAddress) {
    return {
      id: address.id,
      alias: address.alias ?? null,
      text: formatAddress(address),
      lat: address.lat == null ? null : Number(address.lat),
      lng: address.lng == null ? null : Number(address.lng),
      instrucciones: address.instrucciones ?? null,
    };
  }

  function getModeMeta() {
    if (tipoPedido === 'delivery') {
      return {
        icon: 'bicycle-outline' as const,
        title: 'Delivery',
        subtitle: 'Entrega a domicilio',
        detail: ubicacionVisual || 'Confirma tu direccion de entrega',
      };
    }

    if (tipoPedido === 'pickup') {
      return {
        icon: 'bag-handle-outline' as const,
        title: 'Pickup',
        subtitle: 'Recoges en sucursal',
        detail: ubicacionVisual || selectedBranch?.direccion || 'Sucursal seleccionada',
      };
    }

    if (tipoPedido === 'eat_in') {
      return {
        icon: 'restaurant-outline' as const,
        title: 'Comer aqui',
        subtitle: 'Pedido en restaurante',
        detail: tableSession
          ? `${tableSession.mesaLabel} · ${selectedBranch?.nombre || 'Sucursal seleccionada'}`
          : 'Escanea el QR de tu mesa',
      };
    }

    return {
      icon: 'options-outline' as const,
      title: 'Metodo pendiente',
      subtitle: 'Selecciona el metodo desde el inicio',
      detail: 'Vuelve al menu para elegir como recibir tu pedido.',
    };
  }

  const modeMeta = getModeMeta();

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backButton}
          onPress={() => router.back()}
          accessibilityLabel="Volver atras"
          accessibilityRole="button"
          testID="back-btn"
        >
          <Ionicons name="arrow-back" size={22} color={Colors.text || '#111827'} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Revisar pedido</Text>
        <View style={{ width: 40 }} />
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.welcomeSection}>
          <Text style={styles.mainLabel}>Revisa tu pedido</Text>
          <Text style={styles.subLabel}>{modeMeta.subtitle}</Text>
        </View>

        <View style={styles.modeSummary}>
          <View style={styles.modeIcon}>
            <Ionicons name={modeMeta.icon} size={26} color={Colors.primary || '#111827'} />
          </View>
          <View style={styles.modeCopy}>
            <Text style={styles.modeTitle}>{modeMeta.title}</Text>
            <Text style={styles.modeDetail} numberOfLines={2}>{modeMeta.detail}</Text>
          </View>
        </View>

        {tipoPedido === 'eat_in' ? (
          <View style={styles.locationContainer}>
            <View style={styles.locationHeader}>
              <Ionicons name="restaurant" size={20} color={Colors.primary || '#111827'} />
              <Text style={styles.locationTitle}>Cuenta abierta</Text>
            </View>

            <View style={styles.locationBox}>
              <Text style={styles.locationAddress}>
                Tu pedido quedara asociado a la mesa escaneada. Al pagar se generara un QR de salida para que hostess cierre tu visita.
              </Text>

              {tableSession ? (
                <View style={styles.scannedTableBox}>
                  <Ionicons name="qr-code-outline" size={20} color={Colors.primary || '#111827'} />
                  <View style={{ flex: 1 }}>
                    <Text style={styles.scannedTableLabel}>{tableSession.mesaLabel}</Text>
                    <Text style={styles.locationTextMuted}>{selectedBranch?.nombre || 'Sucursal seleccionada'}</Text>
                  </View>
                </View>
              ) : (
                <TouchableOpacity
                  style={styles.scanTableButton}
                  onPress={() => router.push({ pathname: '/table-scanner', params: { returnTo: '/checkout/order-type' } })}
                >
                  <Ionicons name="qr-code-outline" size={18} color="#FFFFFF" />
                  <Text style={styles.scanTableButtonText}>Escanear QR de mesa</Text>
                </TouchableOpacity>
              )}
            </View>
          </View>
        ) : null}

        {tipoPedido === 'delivery' ? (
          <View style={styles.locationContainer}>
            {direccionesGuardadas.length > 0 && (
              <View style={{ marginBottom: 10 }}>
                <Text style={styles.locationTitle}>Tus direcciones</Text>
                <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginTop: 8 }}>
                  {direccionesGuardadas.map((dir) => (
                    <TouchableOpacity
                      key={dir.id}
                      style={[styles.addressChip, direccionSeleccionada?.id === dir.id && styles.addressChipActive]}
                      onPress={() => {
                        setDireccionSeleccionada(dir);
                        setUbicacionVisual(formatAddress(dir));
                        setDeliveryAddress(addressToSelection(dir));
                        setShowMap(false);
                      }}
                      accessibilityLabel={`Seleccionar direccion: ${dir.alias || 'guardada'}`}
                      accessibilityRole="radio"
                      accessibilityState={{ selected: direccionSeleccionada?.id === dir.id }}
                      testID={`address-chip-${dir.id}`}
                    >
                      <Ionicons name="home-outline" size={14} color={direccionSeleccionada?.id === dir.id ? '#FFF' : '#6B7280'} />
                      <Text style={[styles.addressChipText, direccionSeleccionada?.id === dir.id && { color: '#FFF' }]}>
                        {dir.alias || 'Direccion'}
                      </Text>
                    </TouchableOpacity>
                  ))}
                  <TouchableOpacity
                    style={styles.addressChip}
                    onPress={() => {
                      setDireccionSeleccionada(null);
                      setDeliveryAddress(null);
                      setShowMap(true);
                      obtenerUbicacionGPS();
                    }}
                    accessibilityLabel="Agregar nueva direccion"
                    accessibilityRole="button"
                    testID="add-address-btn"
                  >
                    <Ionicons name="add" size={16} color={Colors.primary} />
                    <Text style={{ color: Colors.primary, fontWeight: '600' }}>Nueva</Text>
                  </TouchableOpacity>
                </ScrollView>
              </View>
            )}

            <View style={styles.locationHeader}>
              <Ionicons name="location" size={20} color={Colors.primary || '#111827'} />
              <Text style={styles.locationTitle}>Direccion de entrega</Text>
            </View>

            <View style={styles.locationBox}>
              {coords && (showMap || direccionesGuardadas.length === 0) && (
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
                  <Text style={styles.locationTextMuted}>Detectando tu ubicacion...</Text>
                </View>
              ) : (
                <>
                  <Text style={styles.locationAddress} numberOfLines={2}>
                    {ubicacionVisual || 'Confirma tu direccion de entrega'}
                  </Text>

                  <TouchableOpacity
                    style={styles.changeButton}
                    onPress={handleCambiarUbicacion}
                    activeOpacity={0.7}
                    accessibilityLabel="Cambiar direccion de entrega"
                    accessibilityRole="button"
                    testID="change-address-btn"
                  >
                    <Text style={styles.changeButtonText}>Cambiar direccion</Text>
                    <Ionicons name="create-outline" size={16} color={Colors.primary || '#111827'} />
                  </TouchableOpacity>
                </>
              )}
            </View>
          </View>
        ) : null}

        <View style={styles.summaryContainer}>
          <Text style={styles.summaryTitle}>Resumen del pedido</Text>
          <View style={styles.ticketBox}>
            {items.map((item) => {
              const {
                selectedExtras,
                baseUnit,
                unitTotal,
                lineTotal,
              } = getItemCostBreakdown(item);

              return (
              <View key={item.id} style={styles.summaryItemBlock}>
                <View style={styles.summaryRow}>
                  <Text style={styles.summaryItem} numberOfLines={1}>
                    <Text style={styles.itemQuantity}>{item.cantidad}x</Text> {item.platillo.nombre}
                  </Text>
                  <Text style={styles.summaryPrice}>${lineTotal.toFixed(2)}</Text>
                </View>

                <View style={styles.summaryBreakdown}>
                  <View style={styles.summaryBreakdownRow}>
                    <Text style={styles.summaryBreakdownLabel}>Plato base</Text>
                    <Text style={styles.summaryBreakdownValue}>${baseUnit.toFixed(2)} c/u</Text>
                  </View>

                  {selectedExtras.map((extra) => (
                    <View key={extra.key} style={styles.summaryBreakdownRow}>
                      <Text style={styles.summaryExtraText} numberOfLines={1}>+ {extra.nombre}</Text>
                      <Text style={styles.summaryExtraPrice}>+${extra.precio.toFixed(2)} c/u</Text>
                    </View>
                  ))}

                  <View style={[styles.summaryBreakdownRow, styles.summaryUnitTotalRow]}>
                    <Text style={styles.summaryUnitTotalLabel}>Total del plato</Text>
                    <Text style={styles.summaryUnitTotalValue}>${unitTotal.toFixed(2)} c/u</Text>
                  </View>
                </View>
              </View>
              );
            })}
            <View style={styles.divider} />
            <View style={styles.totalRow}>
              <Text style={styles.totalLabel}>Total a pagar</Text>
              <Text style={styles.totalValue}>${orderTotal.toFixed(2)} MXN</Text>
            </View>
          </View>
        </View>
      </ScrollView>

      <View style={styles.footer}>
        <Button
          label={tipoPedido === 'eat_in' ? 'Pedir' : 'Continuar al pago'}
          onPress={handleContinue}
          fullWidth
          size="lg"
          loading={loading}
          disabled={!tipoPedido || loadingLocation || (tipoPedido === 'eat_in' && !tableSession)}
          style={styles.actionButton}
          accessibilityLabel="Continuar al pago"
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
  },
  content: {
    padding: Spacing.base || 16,
    paddingBottom: 140,
    gap: 24,
  },
  welcomeSection: {
    gap: 4,
    marginTop: 8,
  },
  mainLabel: {
    fontSize: 24,
    fontWeight: '800',
    color: '#111827',
  },
  subLabel: {
    fontSize: 15,
    color: '#6B7280',
  },
  modeSummary: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    gap: 12,
    borderRadius: 16,
    backgroundColor: '#F9FAFB',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  modeIcon: {
    width: 50,
    height: 50,
    borderRadius: 25,
    backgroundColor: '#FFFFFF',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modeCopy: {
    flex: 1,
    minWidth: 0,
  },
  modeTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: '#111827',
  },
  modeDetail: {
    fontSize: 13,
    color: '#6B7280',
    marginTop: 3,
    lineHeight: 18,
  },
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
  scannedTableBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 12,
    borderRadius: 12,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  scannedTableLabel: {
    fontSize: 15,
    fontWeight: '800',
    color: '#111827',
  },
  scanTableButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 12,
    borderRadius: 12,
    backgroundColor: Colors.primary || '#111827',
  },
  scanTableButtonText: {
    color: '#FFFFFF',
    fontWeight: '800',
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
  summaryItemBlock: {
    paddingVertical: 4,
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
    color: '#111827',
  },
  summaryBreakdown: {
    marginLeft: 25,
    gap: 3,
    paddingRight: 8,
    paddingTop: 2,
    paddingBottom: 6,
  },
  summaryBreakdownRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  summaryBreakdownLabel: {
    fontSize: 12,
    color: '#6B7280',
    fontWeight: '600',
  },
  summaryBreakdownValue: {
    fontSize: 12,
    color: '#6B7280',
    fontWeight: '700',
  },
  summaryExtraText: {
    flex: 1,
    fontSize: 12,
    color: '#6B7280',
    fontWeight: '700',
  },
  summaryExtraPrice: {
    fontSize: 12,
    color: '#6B7280',
    fontWeight: '800',
  },
  summaryUnitTotalRow: {
    marginTop: 2,
  },
  summaryUnitTotalLabel: {
    fontSize: 12,
    color: '#111827',
    fontWeight: '800',
  },
  summaryUnitTotalValue: {
    fontSize: 12,
    color: '#111827',
    fontWeight: '900',
  },
  divider: {
    height: 1,
    backgroundColor: '#E5E7EB',
    marginVertical: 12,
  },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 4,
  },
  totalLabel: {
    fontSize: 15,
    fontWeight: '700',
    color: '#111827',
  },
  totalValue: {
    fontSize: 18,
    fontWeight: '800',
    color: Colors.primary || '#111827',
  },
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
  },
});
