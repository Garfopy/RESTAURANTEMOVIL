import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  ScrollView,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useOrders } from '../../hooks/useOrders';
import { Skeleton } from '../../components/ui/Skeleton';
import { EmptyState } from '../../components/ui/EmptyState';
import { Colors, Spacing, Typography, Shadows } from '../../theme';
import type { Pedido } from '@amare/types';

const ESTADO_COLOR: Record<string, string> = {
  pendiente: Colors.warning || '#F59E0B',
  en_preparacion: Colors.accent || '#3B82F6',
  listo: Colors.success || '#10B981',
  en_camino: Colors.info || '#6366F1',
  entregado: Colors.success || '#10B981',
  cancelado: Colors.error || '#EF4444',
};

const ESTADO_LABEL: Record<string, string> = {
  pendiente: 'Pendiente',
  en_preparacion: 'Preparando',
  listo: 'Listo',
  en_camino: 'En camino',
  entregado: 'Entregado',
  cancelado: 'Cancelado',
};

function isEatInConsumption(order: Pedido) {
  return (
    order.tipo_pedido === 'eat_in' &&
    (order.es_consumo || Boolean(order.consumo_id) || Number(order.pedidos_count ?? 0) > 1 || Number(order.cuenta_abierta ?? 0) === 1)
  );
}

function getOrderStatusLabel(order: Pedido) {
  if (order.tipo_pedido !== 'eat_in') {
    return ESTADO_LABEL[order.estado] ?? order.estado;
  }

  if (order.salida_validado_at) return 'Cerrada';
  if (order.salida_qr_generado_at) return 'Pagada';
  if (Number(order.cuenta_abierta ?? 0) === 1 || isEatInConsumption(order)) return 'Cuenta abierta';

  return ESTADO_LABEL[order.estado] ?? order.estado;
}

function getOrderStatusColor(order: Pedido) {
  if (order.tipo_pedido === 'eat_in') {
    if (order.salida_validado_at) return Colors.success || '#10B981';
    if (order.salida_qr_generado_at) return Colors.info || '#6366F1';
    return Colors.primary || '#111827';
  }

  return ESTADO_COLOR[order.estado] || '#6B7280';
}

function getOrderTitle(order: Pedido) {
  if (isEatInConsumption(order)) {
    return order.mesa_nombre ? `Consumo en ${order.mesa_nombre}` : 'Consumo en mesa';
  }

  return order.folio ?? `Pedido #${order.id}`;
}

function getOrderModeMeta(order: Pedido) {
  if (order.tipo_pedido === 'delivery') {
    return { icon: 'bicycle-outline' as const, label: 'A domicilio' };
  }

  if (order.tipo_pedido === 'eat_in') {
    const count = Number(order.pedidos_count ?? 0);
    return {
      icon: 'restaurant-outline' as const,
      label: count > 1 ? `Comer aqui · ${count} tandas` : 'Comer aqui',
    };
  }

  return { icon: 'bag-handle-outline' as const, label: 'Para llevar' };
}

export default function OrdersScreen() {
  const router = useRouter();
  const { data: orders, isLoading } = useOrders();

  function handleOrder(order: Pedido) {
    router.push({ pathname: '/order/[id]', params: { id: String(order.id) } });
  }

  // Skeletons de Alta Fidelidad (Estilo Pro)
  if (isLoading) {
    return (
      <SafeAreaView style={styles.safe}>
        <View style={styles.header}>
          <Text style={styles.headerTitle}>Mis pedidos</Text>
        </View>
        <ScrollView contentContainerStyle={styles.list} showsVerticalScrollIndicator={false}>
          {[1, 2, 3].map((k) => (
            <View key={k} style={styles.skeletonCard}>
              <View style={styles.cardHeader}>
                <Skeleton height={18} width="50%" borderRadius={6} />
                <Skeleton height={24} width="25%" borderRadius={12} />
              </View>
              <View style={[styles.cardTop, { marginTop: 8 }]}>
                <Skeleton height={22} width="40%" borderRadius={6} />
                <Skeleton height={22} width="30%" borderRadius={6} />
              </View>
              <View style={styles.skeletonDivider} />
              <View style={styles.cardFooter}>
                <Skeleton height={16} width="60%" borderRadius={6} />
                <Skeleton height={16} width="5%" borderRadius={6} />
              </View>
            </View>
          ))}
        </ScrollView>
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
          showsVerticalScrollIndicator={false}
          renderItem={({ item }) => {
            const estadoColor = getOrderStatusColor(item);
            const modeMeta = getOrderModeMeta(item);
            return (
              <TouchableOpacity
                style={styles.card}
                onPress={() => handleOrder(item)}
                activeOpacity={0.7}
              >
                {/* Cabecera de la Tarjeta */}
                <View style={styles.cardHeader}>
                  <View style={styles.restaurantInfo}>
                    <Ionicons name="restaurant" size={16} color={Colors.primary || '#111827'} />
                    <Text style={styles.restaurantName} numberOfLines={1}>
                      {item.restaurante_nombre ?? 'Amare Restaurante'}
                    </Text>
                  </View>
                  <View style={[styles.badge, { backgroundColor: `${estadoColor}12` }]}>
                    <Text style={[styles.badgeText, { color: estadoColor }]}>
                      {getOrderStatusLabel(item)}
                    </Text>
                  </View>
                </View>

                {/* Contenido Central */}
                <View style={styles.cardTop}>
                  <Text style={styles.folio}>{getOrderTitle(item)}</Text>
                  <Text style={styles.total}>${item.total?.toFixed(2) ?? '—'} MXN</Text>
                </View>

                {/* Pie de la Tarjeta */}
                <View style={styles.cardFooter}>
                  <View style={styles.detailRow}>
                    <Ionicons 
                      name={modeMeta.icon}
                      size={15} 
                      color="#6B7280" 
                    />
                    <Text style={styles.detailText}>
                      {modeMeta.label}
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
                  <Ionicons name="chevron-forward" size={16} color="#9CA3AF" />
                </View>
              </TouchableOpacity>
            );
          }}
        />
      ) : (
        <EmptyState
          icon="bag-outline"
          title="Sin pedidos aún"
          description="Tus pedidos activos e historial aparecerán en esta sección."
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { 
    flex: 1, 
    backgroundColor: Colors.background || '#F9FAFB' 
  },
  header: {
    paddingHorizontal: 24,
    paddingTop: 24,
    paddingBottom: 16,
  },
  headerTitle: { 
    fontSize: 34, 
    fontWeight: '800', 
    color: Colors.text || '#111827', 
    letterSpacing: -0.8,
    lineHeight: 42,
    paddingTop: 4,
  },
  list: { 
    paddingHorizontal: 24, 
    paddingBottom: 120, 
    paddingTop: 8 
  },
  card: {
    backgroundColor: '#FFFFFF',
    borderRadius: 24,
    padding: 20,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    ...Shadows.md,
  },
  cardHeader: { 
    flexDirection: 'row', 
    justifyContent: 'space-between', 
    alignItems: 'center', 
    marginBottom: 16 
  },
  restaurantInfo: { 
    flexDirection: 'row', 
    alignItems: 'center', 
    gap: 8, 
    flex: 1 
  },
  restaurantName: { 
    fontSize: 15, 
    fontWeight: '700', 
    color: '#111827',
    letterSpacing: -0.2
  },
  cardTop: { 
    flexDirection: 'row', 
    justifyContent: 'space-between', 
    alignItems: 'baseline', 
    marginBottom: 16 
  },
  folio: { 
    fontSize: 16, 
    fontWeight: '500', 
    color: '#4B5563',
  },
  total: { 
    fontSize: 18, 
    fontWeight: '800', 
    color: Colors.primary || '#111827',
    letterSpacing: -0.3
  },
  badge: { 
    paddingHorizontal: 12, 
    paddingVertical: 6, 
    borderRadius: 12 
  },
  badgeText: { 
    fontSize: 12, 
    fontWeight: '700',
  },
  cardFooter: { 
    flexDirection: 'row', 
    justifyContent: 'space-between', 
    alignItems: 'center',
    paddingTop: 16,
    borderTopWidth: 1,
    borderTopColor: '#F3F4F6'
  },
  detailRow: { 
    flexDirection: 'row', 
    alignItems: 'center', 
    gap: 6 
  },
  detailText: { 
    fontSize: 13, 
    color: '#6B7280', 
    fontWeight: '600' 
  },
  dot: { 
    color: '#D1D5DB', 
    fontSize: 14,
    marginHorizontal: 2
  },
  fecha: { 
    fontSize: 13, 
    color: '#9CA3AF', 
    fontWeight: '400' 
  },
  // Estilos exclusivos del estado de carga profesional
  skeletonCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 24,
    padding: 20,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  skeletonDivider: {
    height: 1,
    backgroundColor: '#F3F4F6',
    marginVertical: 16,
  }
});
