import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  SafeAreaView,
  TouchableOpacity,
  ViewStyle,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useOrders } from '../../hooks/useOrders';
import { Skeleton } from '../../components/ui/Skeleton';
import { EmptyState } from '../../components/ui/EmptyState';
import { Colors, Spacing, Typography, Shadows } from '../../theme';
import type { Pedido } from '@amare/types';

const ESTADO_COLOR: Record<string, string> = {
  pendiente: Colors.warning,
  en_preparacion: Colors.accent,
  listo: Colors.success,
  en_camino: Colors.info,
  entregado: Colors.success,
  cancelado: Colors.error,
};

const ESTADO_LABEL: Record<string, string> = {
  pendiente: 'Pendiente',
  en_preparacion: 'Preparando',
  listo: 'Listo',
  en_camino: 'En camino',
  entregado: 'Entregado',
  cancelado: 'Cancelado',
};

export default function OrdersScreen() {
  const router = useRouter();
  const { data: orders, isLoading } = useOrders();

  function handleOrder(order: Pedido) {
    router.push({ pathname: '/order/[id]', params: { id: String(order.id) } });
  }

  if (isLoading) {
    return (
      <SafeAreaView style={styles.safe}>
        <View style={styles.header}><Text style={styles.headerTitle}>Mis pedidos</Text></View>
        {[1, 2, 3].map((k) => (
          <View key={k} style={{ padding: Spacing.base, gap: 8 }}>
            <Skeleton height={80} borderRadius={12} />
          </View>
        ))}
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Mis pedidos</Text>
      </View>
      {orders && orders.length > 0 ? (
        <FlatList
          data={orders}
          keyExtractor={(o) => String(o.id)}
          contentContainerStyle={styles.list}
          renderItem={({ item }) => (
            <TouchableOpacity
              style={styles.card}
              onPress={() => handleOrder(item)}
              activeOpacity={0.8}
            >
              <View style={styles.cardHeader}>
                <View style={styles.restaurantInfo}>
                  <Ionicons name="restaurant-outline" size={16} color={Colors.primary} />
                  <Text style={styles.restaurantName} numberOfLines={1}>
                    {item.restaurante_nombre ?? 'Amare Restaurante'}
                  </Text>
                </View>
                <View style={[styles.badge, { backgroundColor: ESTADO_COLOR[item.estado] + '15' }]}>
                  <Text style={[styles.badgeText, { color: ESTADO_COLOR[item.estado] }]}>
                    {ESTADO_LABEL[item.estado] ?? item.estado}
                  </Text>
                </View>
              </View>

              <View style={styles.cardTop}>
                <Text style={styles.folio}>{item.folio ?? `Pedido #${item.id}`}</Text>
                <Text style={styles.total}>${item.total?.toFixed(2) ?? '—'} MXN</Text>
              </View>

              <View style={styles.cardFooter}>
                <View style={styles.detailRow}>
                  <Ionicons 
                    name={item.tipo_pedido === 'delivery' ? 'bicycle-outline' : 'bag-handle-outline'} 
                    size={14} 
                    color={Colors.textMuted} 
                  />
                  <Text style={styles.detailText}>
                    {item.tipo_pedido === 'delivery' ? 'A domicilio' : 'Para llevar'}
                  </Text>
                  <Text style={styles.dot}>•</Text>
                  <Text style={styles.fecha}>
                    {new Date(item.created_at).toLocaleDateString('es-MX', {
                      day: 'numeric',
                      month: 'short',
                      hour: '2-digit',
                      minute: '2-digit',
                    })}
                  </Text>
                </View>
                <Ionicons name="chevron-forward" size={16} color={Colors.textMuted} />
              </View>
            </TouchableOpacity>
          )}
        />
      ) : (
        <EmptyState
          icon="bag-outline"
          title="Sin pedidos aún"
          description="Tus pedidos aparecerán aquí."
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  header: {
    paddingHorizontal: 20,
    paddingVertical: 16,
  },
  headerTitle: { fontSize: 24, fontWeight: '800', color: Colors.text, letterSpacing: -0.5 },
  list: { paddingHorizontal: 20, paddingBottom: 120, paddingTop: 8 },
  card: {
    backgroundColor: '#FFFFFF',
    borderRadius: 20,
    padding: 16,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: '#F3F4F6',
    ...Shadows.sm,
  },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 },
  restaurantInfo: { flexDirection: 'row', alignItems: 'center', gap: 6, flex: 1 },
  restaurantName: { fontSize: 13, fontWeight: '600', color: Colors.textMuted, textTransform: 'uppercase', letterSpacing: 0.5 },
  cardTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end', marginBottom: 12 },
  folio: { fontSize: 17, fontWeight: '700', color: Colors.text, letterSpacing: -0.3 },
  total: { fontSize: 16, fontWeight: '800', color: Colors.primary },
  badge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 10 },
  badgeText: { fontSize: 11, fontWeight: '800', textTransform: 'uppercase' },
  cardFooter: { 
    flexDirection: 'row', 
    justifyContent: 'space-between', 
    alignItems: 'center',
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: '#F9FAFB'
  },
  detailRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  detailText: { fontSize: 13, color: Colors.textMuted, fontWeight: '500' },
  dot: { color: '#D1D5DB', fontSize: 14 },
  fecha: { fontSize: 13, color: Colors.textMuted, fontWeight: '400' },
});