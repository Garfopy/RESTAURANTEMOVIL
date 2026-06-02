import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  SafeAreaView,
  TouchableOpacity,
} from 'react-native';
import { useRouter } from 'expo-router';
import { useOrders } from '../../hooks/useOrders';
import { Skeleton } from '../../components/ui/Skeleton';
import { EmptyState } from '../../components/ui/EmptyState';
import { Colors, Spacing, Typography } from '../../theme';
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
          contentContainerStyle={{ paddingBottom: 100 }}
          renderItem={({ item }) => (
            <TouchableOpacity
              style={styles.card}
              onPress={() => handleOrder(item)}
              activeOpacity={0.8}
            >
              <View style={styles.cardTop}>
                <Text style={styles.folio}>{item.folio ?? `Pedido #${item.id}`}</Text>
                <View style={[styles.badge, { backgroundColor: ESTADO_COLOR[item.estado] + '22' }]}>
                  <Text style={[styles.badgeText, { color: ESTADO_COLOR[item.estado] }]}>
                    {ESTADO_LABEL[item.estado] ?? item.estado}
                  </Text>
                </View>
              </View>
              <Text style={styles.fecha}>
                {new Date(item.created_at).toLocaleDateString('es-MX', {
                  year: 'numeric',
                  month: 'short',
                  day: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit',
                })}
              </Text>
              <Text style={styles.total}>Total: ${item.total?.toFixed(2) ?? '—'} MXN</Text>
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
    paddingHorizontal: Spacing.base,
    paddingVertical: Spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  headerTitle: { ...Typography.h2, fontWeight: '700', color: Colors.text },
  card: {
    margin: Spacing.base,
    marginBottom: 0,
    backgroundColor: Colors.surface,
    borderRadius: 14,
    padding: Spacing.md,
    gap: 6,
  },
  cardTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  folio: { fontSize: 15, fontWeight: '700', color: Colors.text },
  badge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 20 },
  badgeText: { fontSize: 12, fontWeight: '700' },
  fecha: { fontSize: 13, color: Colors.textMuted },
  total: { fontSize: 14, fontWeight: '600', color: Colors.primary },
});
