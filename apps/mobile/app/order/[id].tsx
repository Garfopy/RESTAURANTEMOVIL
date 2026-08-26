import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Platform,
  RefreshControl,
  ActivityIndicator,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useQuery } from '@tanstack/react-query';
import { apiClient, formatImageUrl } from '../../services/api';
import { getOrderTracking, getPickupOrderById } from '../../services/orders.service';
import { reorderPastOrder } from '../../services/reorder.service';
import { useUserStore } from '../../store/user.store';
import { Colors, Shadows, FontFamily } from '../../theme';
import { Skeleton } from '../../components/ui/Skeleton';
import { OrderTimeline } from '../../components/tracking/OrderTimeline';
import { useToast } from '../../context/ToastContext';
import type { Pedido, TrackingEvent } from '@amare/types';

const ESTADO_INFO: Record<string, { label: string; color: string; icon: string }> = {
  pendiente: { label: 'Recibido', color: Colors.warning, icon: 'time-outline' },
  en_preparacion: { label: 'Preparando', color: Colors.accentDark, icon: 'restaurant-outline' },
  listo: { label: 'Listo para recoger', color: Colors.success, icon: 'checkmark-circle-outline' },
  entregado: { label: 'Entregado', color: Colors.success, icon: 'ribbon-outline' },
  cancelado: { label: 'Cancelado', color: Colors.error, icon: 'close-circle-outline' },
};

function buildOrderTimeline(order: Pedido | null | undefined): TrackingEvent[] {
  if (!order) return [];

  if (order.estado === 'cancelado') {
    return [
      {
        estado: 'pendiente',
        label: 'Pedido recibido',
        descripcion: 'Tu orden entró al restaurante.',
        completado: true,
        en_curso: false,
        timestamp: order.created_at ?? null,
      },
      {
        estado: 'cancelado',
        label: 'Pedido cancelado',
        descripcion: 'Este pedido fue cancelado y no continuará su preparación.',
        completado: true,
        en_curso: false,
        timestamp: order.updated_at ?? order.created_at ?? null,
      },
    ];
  }

  const statusOrder: Array<Pedido['estado']> = ['pendiente', 'en_preparacion', 'listo', 'entregado'];
  const currentIndex = Math.max(0, statusOrder.indexOf(order.estado ?? 'pendiente'));
  return [
    {
      estado: 'pendiente',
      label: 'Pedido recibido',
        descripcion: 'Tu orden entró al restaurante.',
      completado: currentIndex > 0,
      en_curso: currentIndex === 0,
      timestamp: order.created_at ?? null,
    },
    {
      estado: 'en_preparacion',
        label: 'En preparación',
        descripcion: 'Cocina está preparando tus alimentos.',
      completado: currentIndex > 1,
      en_curso: currentIndex === 1,
      timestamp: order.updated_at ?? null,
    },
    {
      estado: 'listo',
      label: 'Listo',
      descripcion: 'Tu pedido está listo para recoger.',
      completado: currentIndex > 2,
      en_curso: currentIndex === 2,
      timestamp: order.updated_at ?? null,
    },
    {
      estado: 'entregado',
      label: 'Entregado',
      descripcion: 'El pedido fue completado.',
      completado: order.estado === 'entregado',
      en_curso: false,
      timestamp: order.estado === 'entregado' ? order.updated_at ?? null : null,
    },
  ];
}

export default function OrderDetailScreen() {
  const { id } = useLocalSearchParams();
  const router = useRouter();
  const user = useUserStore((s) => s.user);
  const toast = useToast();
  const [reordering, setReordering] = useState(false);

  function handleBackToOrders() {
    router.replace('/(tabs)/orders' as never);
  }

  async function handleReorder(currentOrder: Pedido) {
    if (reordering) return;
    setReordering(true);
    try {
      const { addedCount, skippedCount } = await reorderPastOrder(currentOrder);
      if (addedCount === 0) {
        toast.error('Ninguno de los platillos de este pedido está disponible ahora.');
        return;
      }
      toast.success(
        skippedCount > 0
          ? `Agregamos ${addedCount} platillo(s) al carrito. ${skippedCount} ya no están disponibles.`
          : 'Pedido agregado al carrito'
      );
      router.push('/cart');
    } catch {
      toast.error('No pudimos repetir este pedido. Intenta de nuevo.');
    } finally {
      setReordering(false);
    }
  }

  const { data: order, isLoading, isRefetching, refetch } = useQuery({
    queryKey: ['order', id],
    queryFn: async () => {
      try {
        const res = await apiClient.get(`/orders/${id}`, { _suppressConsoleError: true } as any);
        return res.data.data.order;
      } catch (error: any) {
        if (error?.response?.status !== 404) {
          throw error;
        }

        if (user?.id && Number(id) > 0) {
          try {
            return await getPickupOrderById(Number(id), Number(user.id));
          } catch {
            // Ignorar y relanzar el error original de abajo.
          }
        }

        throw error;
      }
    },
    retry: false,
    refetchInterval: (query) => {
      const estado = query.state.data?.estado;
      return estado && estado !== 'entregado' && estado !== 'cancelado' ? 20_000 : false;
    },
  });

  const { data: trackingData, refetch: refetchTracking } = useQuery({
    queryKey: ['order', 'timeline', order?.id],
    queryFn: () => getOrderTracking(Number(order!.id)),
    enabled: Boolean(order?.id),
    staleTime: 30_000,
    retry: false,
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
  const timelineSteps = trackingData?.tracking?.length
    ? trackingData.tracking
    : buildOrderTimeline(order);

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity onPress={handleBackToOrders} style={styles.backBtn}>
          <Ionicons name="arrow-back" size={22} color={Colors.text} />
        </TouchableOpacity>
        <View>
          <Text style={styles.headerTitle}>{order?.folio}</Text>
          <Text style={styles.headerSubtitle}>
            {new Date(order?.created_at ?? '').toLocaleDateString('es-MX', { day: 'numeric', month: 'long' })}
          </Text>
        </View>
        <View style={[styles.statusBadge, { backgroundColor: status.color + '15' }]}>
          <Text style={[styles.statusText, { color: status.color }]}>
            {status.label}
          </Text>
        </View>
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={isRefetching}
            onRefresh={() => {
              void refetch();
              void refetchTracking();
            }}
            tintColor={Colors.primary}
            colors={[Colors.primary]}
          />
        }
      >
        {/* SECCIÓN DE TRACKING VISUAL */}
        <View style={styles.card}>
          <View style={styles.trackingRow}>
            <View style={[styles.iconContainer, { backgroundColor: status.color }]}>
              <Ionicons name={status.icon as any} size={24} color={Colors.white} />
            </View>
            <View style={{ flex: 1, marginLeft: 12 }}>
              <Text style={styles.trackingTitle}>Estado del pedido</Text>
              <Text style={styles.trackingDesc}>Tu orden está siendo procesada en {order?.restaurante_nombre}</Text>
            </View>
          </View>

          <View style={styles.stepperContainer}>
            {['pendiente', 'en_preparacion', 'listo', 'entregado'].map((step, idx) => {
              const isCompleted = ['pendiente', 'en_preparacion', 'listo', 'entregado'].indexOf(order?.estado) >= idx;
              return (
                <React.Fragment key={step}>
                  <View style={[styles.stepDot, isCompleted && { backgroundColor: status.color }]} />
                  {idx < 3 && <View style={[styles.stepLine, isCompleted && { backgroundColor: status.color }]} />}
                </React.Fragment>
              );
            })}
          </View>
          <View style={styles.timelineWrap}>
            <OrderTimeline steps={timelineSteps} />
          </View>
        </View>

        {/* DETALLES DE ENTREGA */}
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Detalles de entrega</Text>
        </View>
        <View style={styles.card}>
          <View style={styles.detailItem}>
            <Ionicons name="storefront-outline" size={20} color={Colors.textMuted} />
            <View style={{ flex: 1, marginLeft: 12 }}>
              <Text style={styles.detailLabel}>Recoges en sucursal</Text>
              <Text style={styles.detailValue}>
                {order?.direccion_entrega || order?.restaurante_nombre}
              </Text>
            </View>
          </View>
          {order?.notas && (
             <View style={[styles.detailItem, { marginTop: 12, borderTopWidth: 1, borderTopColor: Colors.borderLight, paddingTop: 12 }]}>
                <Ionicons name="chatbubble-ellipses-outline" size={20} color={Colors.textMuted} />
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
            const calculateExtrasTotal = (values: any[]) => values.reduce((sum: number, ext: any) => {
              if (Array.isArray(ext.opciones)) {
                return sum + ext.opciones.reduce(
                  (inner: number, option: any) => inner + Number(option.precio_extra || 0) * Number(option.cantidad || 1),
                  0
                );
              }
              return sum + Number(ext.subtotal ?? (Number(ext.precio_unitario || 0) * Number(ext.cantidad || 1)));
            }, 0);
            
            // Fuente primaria: extras_json (nuevo campo estructurado)
            if (item.extras_json) {
              try {
                extras = JSON.parse(item.extras_json);
                extrasTotal = calculateExtrasTotal(extras);
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
                  extrasTotal = calculateExtrasTotal(extras);
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
                    {extras.flatMap((ext: any) => Array.isArray(ext.opciones)
                      ? ext.opciones.map((opc: any) => (
                        <Text key={`${ext.modificador_id}-${opc.opcion_id}`} style={styles.extraItem}>
                          {ext.tipo === 'exclusion' ? '- ' : '+ '}{opc.opcion_nombre}
                          {Number(opc.cantidad || 1) > 1 ? ` x${opc.cantidad}` : ''}
                          {Number(opc.precio_extra || 0) > 0 ? ` ($${Number(opc.precio_extra).toFixed(2)})` : ''}
                        </Text>
                      ))
                      : [(
                        <Text key={`modifier-${ext.modificador_id}`} style={styles.extraItem}>
                          {ext.tipo === 'exclusion' ? '- ' : '+ '}{ext.nombre}
                          {Number(ext.cantidad || 1) > 1 ? ` x${ext.cantidad}` : ''}
                          {Number(ext.subtotal || 0) > 0 ? ` ($${Number(ext.subtotal).toFixed(2)})` : ''}
                        </Text>
                      )])}
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
          <View style={styles.divider} />
          <View style={styles.summaryRow}>
            <Text style={styles.totalLabel}>Total</Text>
            <Text style={styles.totalValue}>${order?.total?.toFixed(2)} MXN</Text>
          </View>
        </View>

        {order ? (
          <TouchableOpacity
            style={styles.reorderButton}
            activeOpacity={0.85}
            onPress={() => handleReorder(order)}
            disabled={reordering}
            accessibilityRole="button"
            accessibilityLabel="Pedir de nuevo"
          >
            {reordering ? (
              <ActivityIndicator size="small" color={Colors.white} />
            ) : (
              <Ionicons name="repeat-outline" size={19} color={Colors.white} />
            )}
            <Text style={styles.reorderButtonText}>
              {reordering ? 'Agregando…' : 'Pedir de nuevo'}
            </Text>
          </TouchableOpacity>
        ) : null}

        <TouchableOpacity
          style={styles.helpButton}
          activeOpacity={0.8}
          onPress={() => router.push('/profile/help' as never)}
          accessibilityRole="button"
          accessibilityLabel="Necesito ayuda con mi pedido"
        >
          <Ionicons name="help-circle-outline" size={20} color={Colors.primary} />
          <Text style={styles.helpButtonText}>Necesito ayuda con mi pedido</Text>
        </TouchableOpacity>

      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingVertical: 16,
    backgroundColor: Colors.surface,
    borderBottomWidth: 1,
    borderBottomColor: Colors.borderLight,
  },
  backBtn: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: Colors.borderLight,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  headerTitle: { fontFamily: FontFamily.heading, fontSize: 19, color: Colors.text },
  headerSubtitle: { fontSize: 13, color: Colors.textMuted },
  statusBadge: {
    marginLeft: 'auto',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
  },
  statusText: { fontSize: 12, fontWeight: '700', textTransform: 'uppercase' },

  content: { padding: 20, gap: 16 },

  card: {
    backgroundColor: Colors.surface,
    borderRadius: 24,
    padding: 20,
    ...Shadows.sm,
    borderWidth: 1,
    borderColor: Colors.borderLight,
  },
  timelineWrap: {
    marginTop: 18,
    marginHorizontal: -20,
  },

  trackingRow: { flexDirection: 'row', alignItems: 'center' },
  iconContainer: { width: 48, height: 48, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
  trackingTitle: { fontSize: 16, fontWeight: '700', color: Colors.text },
  trackingDesc: { fontSize: 13, color: Colors.textMuted, marginTop: 2 },
  
  stepperContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 24,
    paddingHorizontal: 10,
  },
  stepDot: { width: 10, height: 10, borderRadius: 5, backgroundColor: Colors.border },
  stepLine: { flex: 1, height: 3, backgroundColor: Colors.border, marginHorizontal: 4 },

  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 8,
    paddingHorizontal: 4,
  },
  sectionTitle: { fontSize: 15, fontWeight: '700', color: Colors.text },
  itemCount: { fontSize: 13, color: Colors.textMuted },

  detailItem: { flexDirection: 'row', alignItems: 'flex-start' },
  detailLabel: { fontSize: 12, color: Colors.textMuted, fontWeight: '600', textTransform: 'uppercase' },
  detailValue: { fontSize: 14, color: Colors.text, fontWeight: '500', marginTop: 2 },

  productRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 16 },
  borderTop: { borderTopWidth: 1, borderTopColor: Colors.borderLight },
  imgWrapper: { position: 'relative' },
  productImg: { width: 64, height: 64, borderRadius: 16, backgroundColor: Colors.borderLight },
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
    borderColor: Colors.surface,
  },
  qtyText: { color: Colors.white, fontSize: 10, fontWeight: '800' },
  productName: { fontSize: 15, fontWeight: '700', color: Colors.text, marginBottom: 4 },
  priceBreakdown: { marginTop: 2 },
  productPrice: { fontSize: 13, color: Colors.textMuted },
  extraItem: { fontSize: 12, color: Colors.accentDark, fontWeight: '500', marginLeft: 4, marginTop: 1 },
  unitPrice: { fontSize: 12, color: Colors.textSecondary, fontWeight: '700', marginTop: 2 },
  notasText: { fontSize: 11, color: Colors.textMuted, fontStyle: 'italic', marginTop: 4 },
  productSubtotal: {
    fontSize: 15,
    fontWeight: '800',
    color: Colors.text,
    marginLeft: 8,
  },

  summaryRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 10 },
  summaryLabel: { fontSize: 14, color: Colors.textMuted },
  summaryValue: { fontSize: 14, fontWeight: '600', color: Colors.text },
  divider: { height: 1, backgroundColor: Colors.borderLight, marginVertical: 12 },
  totalLabel: { fontSize: 16, fontWeight: '700', color: Colors.text },
  totalValue: { fontSize: 18, fontWeight: '800', color: Colors.primary },

  reorderButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    minHeight: 52,
    borderRadius: 16,
    backgroundColor: Colors.primary,
    marginTop: 8,
  },
  reorderButtonText: { fontSize: 15, fontWeight: '700', color: Colors.white },
  helpButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 16,
    marginTop: 4,
    marginBottom: 40,
  },
  helpButtonText: { fontSize: 14, fontWeight: '600', color: Colors.primary },
});
