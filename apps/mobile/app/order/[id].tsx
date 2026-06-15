import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  SafeAreaView,
  TouchableOpacity,
  ActivityIndicator,
  Platform,
  Alert,
} from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useQuery } from '@tanstack/react-query';
import { apiClient, formatImageUrl, getApiError } from '../../services/api';
import { createPaymentIntent } from '../../services/orders.service';
import { Colors, Spacing, Shadows } from '../../theme';
import LottieView from 'lottie-react-native';
import { Skeleton } from '../../components/ui/Skeleton';

const ESTADO_INFO: Record<string, { label: string; color: string; icon: string }> = {
  pendiente: { label: 'Recibido', color: '#F59E0B', icon: 'time-outline' },
  en_preparacion: { label: 'Preparando', color: '#8B5CF6', icon: 'restaurant-outline' },
  listo: { label: 'Listo para recoger', color: '#10B981', icon: 'checkmark-circle-outline' },
  en_camino: { label: 'En Camino', color: '#3B82F6', icon: 'bicycle-outline' },
  entregado: { label: 'Entregado', color: '#10B981', icon: 'ribbon-outline' },
  cancelado: { label: 'Cancelado', color: '#EF4444', icon: 'close-circle-outline' },
};

export default function OrderDetailScreen() {
  const { id } = useLocalSearchParams();
  const router = useRouter();
  const [payingAccount, setPayingAccount] = useState(false);

  const { data: order, isLoading } = useQuery({
    queryKey: ['order', id],
    queryFn: async () => {
      const res = await apiClient.get(`/orders/${id}`);
      return res.data.data.order;
    },
  });

  if (isLoading) return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <Skeleton width={40} height={40} borderRadius={20} />
        <Skeleton width={120} height={20} />
      </View>
      <View style={{ padding: 20, gap: 20 }}>
        <Skeleton height={150} borderRadius={20} />
        <Skeleton height={200} borderRadius={20} />
      </View>
    </SafeAreaView>
  );

  const status = ESTADO_INFO[order?.estado ?? 'pendiente'];
  const isOpenEatInAccount =
    order?.tipo_pedido === 'eat_in' &&
    Number(order?.cuenta_abierta ?? 0) === 1 &&
    !order?.salida_qr_generado_at;

  async function handlePayOpenAccount() {
    if (!order) return;

    try {
      setPayingAccount(true);
      const { client_secret, id: intentId } = await createPaymentIntent({
        order_id: Number(order.id),
        amount: Number(order.total || 0),
        currency: 'mxn',
      });

      router.push({
        pathname: '/checkout/payment',
        params: {
          clientSecret: client_secret,
          intentId,
          restauranteId: String(order.restaurante_id),
          tipoPedido: 'eat_in',
          orderId: String(order.id),
          amount: String(order.total || 0),
          folio: order.folio,
          mesaId: order.mesa_id ? String(order.mesa_id) : '',
          mesaLabel: order.mesa_nombre || (order.mesa_id ? `Mesa ${order.mesa_id}` : ''),
        },
      });
    } catch (error) {
      Alert.alert('Error', getApiError(error) || 'No se pudo iniciar el pago de la cuenta.');
    } finally {
      setPayingAccount(false);
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => router.back()} style={styles.backBtn}>
          <Ionicons name="arrow-back" size={22} color={Colors.text} />
        </TouchableOpacity>
        <View>
          <Text style={styles.headerTitle}>{order?.folio}</Text>
          <Text style={styles.headerSubtitle}>
            {new Date(order?.created_at ?? '').toLocaleDateString('es-MX', { day: 'numeric', month: 'long' })}
          </Text>
        </View>
        <View style={[styles.statusBadge, { backgroundColor: status.color + '15' }]}>
          <Text style={[styles.statusText, { color: status.color }]}>{status.label}</Text>
        </View>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        
        {/* SECCIÓN DE TRACKING VISUAL */}
        <View style={styles.card}>
          <View style={styles.trackingRow}>
            {order?.estado === 'en_camino' ? (
              <View style={styles.lottieContainer}>
                <LottieView
                  source={{ uri: 'https://lottie.host/3629399e-2624-432a-9e2b-231362070f90/sY5i6s131P.json' }} // Animación de scooter de reparto
                  autoPlay
                  loop
                  style={styles.lottieAnimation}
                />
              </View>
            ) : (
              <View style={[styles.iconContainer, { backgroundColor: status.color }]}>
                <Ionicons name={status.icon as any} size={24} color="#FFF" />
              </View>
            )}
            <View style={{ flex: 1, marginLeft: 12 }}>
              <Text style={styles.trackingTitle}>Estado del pedido</Text>
              <Text style={styles.trackingDesc}>Tu orden está siendo procesada en {order?.restaurante_nombre}</Text>
            </View>
          </View>
          
          <View style={styles.stepperContainer}>
            {['pendiente', 'en_preparacion', 'listo', 'entregado'].map((step, idx) => {
              const isCompleted = ['pendiente', 'en_preparacion', 'listo', 'en_camino', 'entregado'].indexOf(order?.estado) >= idx;
              return (
                <React.Fragment key={step}>
                  <View style={[styles.stepDot, isCompleted && { backgroundColor: status.color }]} />
                  {idx < 3 && <View style={[styles.stepLine, isCompleted && { backgroundColor: status.color }]} />}
                </React.Fragment>
              );
            })}
          </View>
        </View>

        {/* DETALLES DE ENTREGA */}
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Detalles de entrega</Text>
        </View>
        <View style={styles.card}>
          <View style={styles.detailItem}>
            <Ionicons name={order?.tipo_pedido === 'delivery' ? "location-outline" : "storefront-outline"} size={20} color="#6B7280" />
            <View style={{ flex: 1, marginLeft: 12 }}>
              <Text style={styles.detailLabel}>{order?.tipo_pedido === 'delivery' ? 'Dirección de envío' : 'Recoges en sucursal'}</Text>
              <Text style={styles.detailValue}>{order?.direccion_entrega || order?.restaurante_nombre}</Text>
            </View>
          </View>
          {order?.notas && (
             <View style={[styles.detailItem, { marginTop: 12, borderTopWidth: 1, borderTopColor: '#F3F4F6', paddingTop: 12 }]}>
                <Ionicons name="chatbubble-ellipses-outline" size={20} color="#6B7280" />
                <View style={{ flex: 1, marginLeft: 12 }}>
                  <Text style={styles.detailLabel}>Notas del pedido</Text>
                  <Text style={styles.detailValue}>{order?.notas}</Text>
                </View>
             </View>
          )}
        </View>

        {/* PRODUCTOS */}
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Tu pedido</Text>
          <Text style={styles.itemCount}>{order?.items?.length} {order?.items?.length === 1 ? 'producto' : 'productos'}</Text>
        </View>
        <View style={styles.card}>
          {order?.items?.map((item: any, index: number) => {
            // Parsear extras: usar extras_json como fuente primaria, fallback a notas legacy
            let notasCliente = '';
            let extras: any[] = [];
            let extrasTotal = 0;
            
            // Fuente primaria: extras_json (nuevo campo estructurado)
            if (item.extras_json) {
              try {
                extras = JSON.parse(item.extras_json);
                extrasTotal = extras.reduce(
                  (sum: number, ext: any) =>
                    sum + (ext.opciones || []).reduce((s: number, o: any) => s + (o.precio_extra || 0), 0),
                  0
                );
              } catch { /* ignorar error de parseo */ }
            }
            
            // Fallback: parsear campo notas legacy
            try {
              if (item.notas && item.notas.startsWith('{')) {
                const parsed = JSON.parse(item.notas);
                notasCliente = parsed.notas || '';
                // Solo usar extras del legacy si no hay extras_json
                if (!item.extras_json) {
                  extras = parsed.extras || [];
                  extrasTotal = extras.reduce(
                    (sum: number, ext: any) =>
                      sum + (ext.opciones || []).reduce((s: number, o: any) => s + (o.precio_extra || 0), 0),
                    0
                  );
                }
              } else {
                notasCliente = item.notas || '';
              }
            } catch {
              notasCliente = item.notas || '';
            }
            const precioConExtras = item.precio_unit;
            const precioBase = item.precio_unit - extrasTotal;

            return (
              <View key={item.id} style={[styles.productRow, index !== 0 && styles.borderTop]}>
                <View style={styles.imgWrapper}>
                  <Image 
                    source={formatImageUrl(item.platillo_imagen) ?? require('../../assets/placeholder-food.jpg')} 
                    style={styles.productImg}
                    contentFit="cover"
                    transition={200}
                  />
                  <View style={styles.qtyBadge}>
                    <Text style={styles.qtyText}>{item.cantidad}</Text>
                  </View>
                </View>
                <View style={{ flex: 1, marginLeft: 12 }}>
                  <Text style={styles.productName} numberOfLines={2}>{item.platillo_nombre}</Text>
                  {/* Desglose de precio */}
                  <View style={styles.priceBreakdown}>
                    <Text style={styles.productPrice}>Precio base: ${precioBase.toFixed(2)}</Text>
                    {extras.map((ext: any) =>
                      ext.opciones.map((opc: any) => (
                        <Text key={`${ext.modificador_id}-${opc.opcion_id}`} style={styles.extraItem}>
                          + {opc.opcion_nombre} (${opc.precio_extra.toFixed(2)})
                        </Text>
                      ))
                    )}
                    {extrasTotal > 0 && (
                      <Text style={styles.unitPrice}>${precioConExtras.toFixed(2)} c/u</Text>
                    )}
                    {extrasTotal === 0 && (
                      <Text style={styles.unitPrice}>${precioConExtras.toFixed(2)} c/u</Text>
                    )}
                  </View>
                  {notasCliente !== '' && (
                    <Text style={styles.notasText} numberOfLines={2}>📝 {notasCliente}</Text>
                  )}
                </View>
                <Text style={styles.productSubtotal}>${(precioConExtras * item.cantidad).toFixed(2)}</Text>
              </View>
            );
          })}
        </View>

        {/* RESUMEN DE PAGO */}
        <View style={styles.card}>
          <View style={styles.summaryRow}>
            <Text style={styles.summaryLabel}>Subtotal</Text>
            <Text style={styles.summaryValue}>${order?.subtotal?.toFixed(2)}</Text>
          </View>
          <View style={styles.summaryRow}>
            <Text style={styles.summaryLabel}>Costo de envío</Text>
            <Text style={styles.summaryValue}>$0.00</Text>
          </View>
          <View style={styles.divider} />
          <View style={styles.summaryRow}>
            <Text style={styles.totalLabel}>Total</Text>
            <Text style={styles.totalValue}>${order?.total?.toFixed(2)} MXN</Text>
          </View>
        </View>

        {isOpenEatInAccount && (
          <TouchableOpacity
            style={styles.payAccountButton}
            onPress={handlePayOpenAccount}
            activeOpacity={0.85}
            disabled={payingAccount}
          >
            {payingAccount ? (
              <ActivityIndicator color="#FFFFFF" />
            ) : (
              <>
                <Ionicons name="card-outline" size={20} color="#FFFFFF" />
                <Text style={styles.payAccountText}>Pagar cuenta y generar QR de salida</Text>
              </>
            )}
          </TouchableOpacity>
        )}

        <TouchableOpacity style={styles.helpButton} activeOpacity={0.8}>
          <Ionicons name="help-circle-outline" size={20} color={Colors.primary} />
          <Text style={styles.helpButtonText}>Necesito ayuda con mi pedido</Text>
        </TouchableOpacity>

      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#F9FAFB' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingVertical: 16,
    backgroundColor: '#FFF',
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  backBtn: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#F3F4F6',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  headerTitle: { fontSize: 18, fontWeight: '800', color: '#111827' },
  headerSubtitle: { fontSize: 13, color: '#6B7280' },
  statusBadge: {
    marginLeft: 'auto',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
  },
  statusText: { fontSize: 12, fontWeight: '700', textTransform: 'uppercase' },
  
  content: { padding: 20, gap: 16 },
  
  card: {
    backgroundColor: '#FFF',
    borderRadius: 24,
    padding: 20,
    ...Shadows.sm,
    borderWidth: 1,
    borderColor: '#F3F4F6',
  },
  
  trackingRow: { flexDirection: 'row', alignItems: 'center' },
  iconContainer: { width: 48, height: 48, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
  trackingTitle: { fontSize: 16, fontWeight: '700', color: '#111827' },
  trackingDesc: { fontSize: 13, color: '#6B7280', marginTop: 2 },
  
  lottieContainer: {
    width: 48, // Mismo tamaño que el iconContainer para mantener la alineación
    height: 48,
    borderRadius: 16,
    overflow: 'hidden', // Asegura que la animación no se desborde
  },
  lottieAnimation: {
    width: '100%',
    height: '100%',
  },
  stepperContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 24,
    paddingHorizontal: 10,
  },
  stepDot: { width: 10, height: 10, borderRadius: 5, backgroundColor: '#E5E7EB' },
  stepLine: { flex: 1, height: 3, backgroundColor: '#E5E7EB', marginHorizontal: 4 },

  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 8,
    paddingHorizontal: 4,
  },
  sectionTitle: { fontSize: 15, fontWeight: '700', color: '#374151' },
  itemCount: { fontSize: 13, color: '#9CA3AF' },

  detailItem: { flexDirection: 'row', alignItems: 'flex-start' },
  detailLabel: { fontSize: 12, color: '#9CA3AF', fontWeight: '600', textTransform: 'uppercase' },
  detailValue: { fontSize: 14, color: '#111827', fontWeight: '500', marginTop: 2 },

  productRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 16 },
  borderTop: { borderTopWidth: 1, borderTopColor: '#F3F4F6' },
  imgWrapper: { position: 'relative' },
  productImg: { width: 64, height: 64, borderRadius: 16, backgroundColor: '#F3F4F6' },
  qtyBadge: {
    position: 'absolute',
    top: -6,
    right: -6,
    backgroundColor: Colors.primary,
    width: 20,
    height: 20,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: '#FFF',
  },
  qtyText: { color: '#FFF', fontSize: 10, fontWeight: '800' },
  productName: { fontSize: 15, fontWeight: '700', color: '#111827', marginBottom: 4 },
  priceBreakdown: { marginTop: 2 },
  productPrice: { fontSize: 13, color: '#6B7280' },
  extraItem: { fontSize: 12, color: '#8B5CF6', fontWeight: '500', marginLeft: 4, marginTop: 1 },
  unitPrice: { fontSize: 12, color: '#374151', fontWeight: '700', marginTop: 2 },
  notasText: { fontSize: 11, color: '#9CA3AF', fontStyle: 'italic', marginTop: 4 },
  productSubtotal: { 
    fontSize: 15, 
    fontWeight: '800', 
    color: '#111827',
    marginLeft: 8,
  },

  summaryRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 10 },
  summaryLabel: { fontSize: 14, color: '#6B7280' },
  summaryValue: { fontSize: 14, fontWeight: '600', color: '#111827' },
  divider: { height: 1, backgroundColor: '#F3F4F6', marginVertical: 12 },
  totalLabel: { fontSize: 16, fontWeight: '700', color: '#111827' },
  totalValue: { fontSize: 18, fontWeight: '800', color: Colors.primary },

  payAccountButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 16,
    paddingHorizontal: 18,
    borderRadius: 16,
    backgroundColor: Colors.primary,
    ...Shadows.md,
  },
  payAccountText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '800',
    textAlign: 'center',
  },

  helpButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 16,
    marginTop: 8,
    marginBottom: 40,
  },
  helpButtonText: { fontSize: 14, fontWeight: '600', color: Colors.primary },
});
