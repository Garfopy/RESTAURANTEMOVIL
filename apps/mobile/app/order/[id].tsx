import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  ScrollView,
  TouchableOpacity,
} from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useOrder, useOrderTracking } from '../../hooks/useOrders';
import { OrderTimeline } from '../../components/tracking/OrderTimeline';
import { Skeleton } from '../../components/ui/Skeleton';
import { Colors, Spacing, Typography } from '../../theme';

const ESTADO_LABEL: Record<string, string> = {
  pendiente: 'Pendiente',
  en_preparacion: 'En preparación',
  listo: 'Listo para recoger',
  en_camino: 'En camino',
  entregado: 'Entregado',
  cancelado: 'Cancelado',
};

const ESTADO_COLOR: Record<string, string> = {
  pendiente: Colors.warning,
  en_preparacion: Colors.accent,
  listo: Colors.success,
  en_camino: Colors.info,
  entregado: Colors.success,
  cancelado: Colors.error,
};

export default function OrderDetailScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const orderId = Number(id);

  const { data: order, isLoading: loadingOrder } = useOrder(orderId);
  const { data: tracking } = useOrderTracking(orderId);

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => router.back()}>
          <Ionicons name="arrow-back" size={24} color={Colors.text} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>
          {order ? `Pedido ${order.folio ?? `#${order.id}`}` : 'Seguimiento'}
        </Text>
        <View style={{ width: 24 }} />
      </View>

      <ScrollView contentContainerStyle={styles.content}>
        {loadingOrder ? (
          <>
            <Skeleton height={28} width="50%" />
            <Skeleton height={16} style={{ marginTop: 8 }} />
          </>
        ) : order ? (
          <>
            {/* Estado actual */}
            <View
              style={[styles.estadoBadge, { backgroundColor: ESTADO_COLOR[order.estado] + '22' }]}
            >
              <View
                style={[styles.estadoDot, { backgroundColor: ESTADO_COLOR[order.estado] }]}
              />
              <Text style={[styles.estadoText, { color: ESTADO_COLOR[order.estado] }]}>
                {ESTADO_LABEL[order.estado] ?? order.estado}
              </Text>
            </View>

            {/* Timeline */}
            {tracking && tracking.length > 0 && (
              <View style={styles.timelineSection}>
                <Text style={styles.sectionTitle}>Seguimiento</Text>
                <OrderTimeline steps={tracking} />
              </View>
            )}

            {/* Items */}
            <View style={styles.itemsSection}>
              <Text style={styles.sectionTitle}>Artículos</Text>
              {order.items?.map((item, i) => (
                <View key={i} style={styles.itemRow}>
                  <Text style={styles.itemQty}>{item.cantidad}x</Text>
                  <Text style={styles.itemNombre} numberOfLines={2}>
                    {item.platillo_id}
                  </Text>
                  <Text style={styles.itemPrice}>
                    ${(item.precio_unit * item.cantidad).toFixed(2)}
                  </Text>
                </View>
              ))}
            </View>
          </>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.base,
    paddingVertical: Spacing.sm,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  headerTitle: { ...Typography.h3, fontWeight: '700', color: Colors.text },
  content: { padding: Spacing.base, gap: Spacing.base, paddingBottom: 40 },
  estadoBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 12,
    alignSelf: 'flex-start',
  },
  estadoDot: { width: 10, height: 10, borderRadius: 5 },
  estadoText: { fontSize: 15, fontWeight: '700' },
  timelineSection: { gap: 10 },
  itemsSection: {
    backgroundColor: Colors.surface,
    borderRadius: 14,
    padding: Spacing.md,
    gap: 8,
  },
  sectionTitle: { fontSize: 15, fontWeight: '700', color: Colors.text },
  itemRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  itemQty: { fontSize: 13, fontWeight: '700', color: Colors.textMuted, minWidth: 24 },
  itemNombre: { flex: 1, fontSize: 13, color: Colors.text },
  itemPrice: { fontSize: 13, fontWeight: '700', color: Colors.primary },
});
