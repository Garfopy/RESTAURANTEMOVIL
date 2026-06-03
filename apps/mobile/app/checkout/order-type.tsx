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
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';

// Stores de la aplicación
import { useCartStore } from '../../store/cart.store';
import { useUserStore } from '../../store/user.store';
import { useBranchStore } from '../../store/branch.store'; // 🌟 Para leer los datos del restaurante

import { createPaymentIntent } from '../../services/orders.service';
import { Button } from '../../components/ui/Button';
import { Colors, Spacing, Shadows } from '../../theme';

const ORDER_TYPES = [
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
];

export default function OrderTypeScreen() {
  const router = useRouter();
  const { items, total, tipoPedido, setTipoPedido, restauranteId } = useCartStore();
  const { sucursales } = useBranchStore();
  
  // 🌟 Estado del usuario para guardar/leer direcciones registradas
  // Nota: Ajusta 'direccionGuardada' y 'setDireccionGuardada' según las propiedades reales de tu useUserStore
  const userState = useUserStore() as any;
  const direccionRegistrada = userState?.direccionGuardada || userState?.user?.direccion || '';
  const actualizarDireccionUsuario = userState?.setDireccionGuardada || userState?.updateProfile;

  // Estados locales para el flujo de ubicación
  const [loading, setLoading] = useState(false);
  const [loadingLocation, setLoadingLocation] = useState(false);
  const [ubicacionVisual, setUbicacionVisual] = useState<string>('');

  // Sincronizar y procesar la ubicación según el tipo de entrega seleccionado
  useEffect(() => {
    if (!tipoPedido) return;

    if (tipoPedido === 'delivery') {
      // 1. Prioridad: Si ya hay una dirección guardada en el perfil del usuario, usar esa.
      if (direccionRegistrada) {
        setUbicacionVisual(direccionRegistrada);
      } else {
        // 2. Si no hay dirección previa, obtener la ubicación GPS actual.
        obtenerUbicacionGPS();
      }
    } else if (tipoPedido === 'pickup') {
      // 3. Si es para llevar, buscar los datos de la sucursal activa en la BD mapeados en el store
      const sucursalActiva = sucursales.find(s => String(s.id) === String(restauranteId));
      if (sucursalActiva) {
        setUbicacionVisual(sucursalActiva.direccion || sucursalActiva.descripcion || 'Sucursal Amare Seleccionada');
      } else {
        setUbicacionVisual('Dirección del restaurante no disponible');
      }
    }
  }, [tipoPedido, direccionRegistrada, restauranteId, sucursales]);

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

      // Convertir coordenadas de latitud y longitud en una dirección legible por humanos
      const reverseGeocode = await Location.reverseGeocodeAsync({
        latitude: location.coords.latitude,
        longitude: location.coords.longitude,
      });

      if (reverseGeocode && reverseGeocode.length > 0) {
        const address = reverseGeocode[0];
        const direccionFormateada = `${address.street || 'Calle'} ${address.name || ''}, Col. ${address.district || address.subregion || ''}, ${address.city || ''}`;
        setUbicacionVisual(direccionFormateada);
        
        // Guardar automáticamente en el estado global para futuras compras si existe la función
        if (typeof actualizarDireccionUsuario === 'function') {
          actualizarDireccionUsuario(direccionFormateada);
        }
      } else {
        setUbicacionVisual('Ubicación detectada por GPS');
      }
    } catch (error) {
      console.error('Error al obtener la ubicación:', error);
      setUbicacionVisual('Dirección no encontrada automáticamente');
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
            onPress: (nuevaDireccion) => {
              if (nuevaDireccion && nuevaDireccion.trim() !== '') {
                setUbicacionVisual(nuevaDireccion);
                if (typeof actualizarDireccionUsuario === 'function') {
                  actualizarDireccionUsuario(nuevaDireccion);
                }
              }
            },
          },
        ],
        'plain-text',
        ubicacionVisual
      );
    }
  }

  async function handleContinue() {
    if (!tipoPedido) {
      Alert.alert('Selección requerida', 'Por favor, elige cómo quieres recibir tu pedido.');
      return;
    }

    if (tipoPedido === 'delivery' && (!ubicacionVisual || ubicacionVisual.includes('...'))) {
      Alert.alert('Dirección requerida', 'Por favor, introduce una dirección de entrega válida.');
      return;
    }

    setLoading(true);
    try {
      const { client_secret, id: intentId } = await createPaymentIntent({
        restaurante_id: restauranteId!,
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
          direccionEntrega: ubicacionVisual, // Enviamos la dirección procesada al checkout
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
        <TouchableOpacity style={styles.backButton} onPress={() => router.back()}>
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
        <View style={styles.cardsContainer}>
          {ORDER_TYPES.map((type) => {
            const isSelected = tipoPedido === type.id;
            return (
              <TouchableOpacity
                key={type.id}
                activeOpacity={0.8}
                style={[styles.squareCard, isSelected && styles.squareCardActive]}
                onPress={() => setTipoPedido(type.id as never)}
              >
                <View style={[styles.iconWrapper, isSelected && styles.iconWrapperActive]}>
                  <Ionicons
                    name={(isSelected ? type.iconActive : type.icon) as never}
                    size={32}
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
            
            <View style={styles.locationBox}>
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
  squareCard: {
    flex: 1,
    aspectRatio: 0.95,
    backgroundColor: '#FFFFFF',
    borderRadius: 20,
    padding: 16,
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
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 14,
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