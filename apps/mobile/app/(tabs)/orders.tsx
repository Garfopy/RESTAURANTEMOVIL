import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  ScrollView,
  RefreshControl,
  ActivityIndicator,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useOrders } from '../../hooks/useOrders';
import { Skeleton } from '../../components/ui/Skeleton';
import { EmptyState } from '../../components/ui/EmptyState';
import { AuthRequiredState } from '../../components/auth/AuthRequiredState';
import { Colors, Shadows, FontFamily } from '../../theme';
import { useUserStore } from '../../store/user.store';
import { reorderPastOrder } from '../../services/reorder.service';
import { useToast } from '../../context/ToastContext';
import type { Pedido } from '@amare/types';

const ESTADO_COLOR: Record<string, string> = {
  pendiente: Colors.warning,
  en_preparacion: Colors.accentDark,
  listo: Colors.success,
  entregado: Colors.success,
  cancelado: Colors.error,
};

const ESTADO_LABEL: Record<string, string> = {
  pendiente: 'Pendiente',
  en_preparacion: 'Preparando',
  listo: 'Listo',
  entregado: 'Entregado',
  cancelado: 'Cancelado',
};

function getOrderStatusLabel(order: Pedido) {
  return ESTADO_LABEL[order.estado] ?? order.estado;
}

function getOrderStatusColor(order: Pedido) {
  return ESTADO_COLOR[order.estado] || Colors.textMuted;
}

function getOrderTitle(order: Pedido) {
  return order.folio ?? `Pedido #${order.id}`;
}

export default function OrdersScreen() {
  const router = useRouter();
  const token = useUserStore((state) => state.token);
  const toast = useToast();
  const { data: orders, isLoading, isRefetching, refetch } = useOrders();
  const [reorderingId, setReorderingId] = useState<number | null>(null);

  if (!token) {
    return (
      <AuthRequiredState
        icon="receipt-outline"
        title="Registra tu cuenta para seguir pedidos"
        message="Guarda tu historial, recibe actualizaciones y vuelve a pedir tus favoritos sin capturar todo otra vez."
        benefits={['Seguimiento', 'Historial', 'Reordenar']}
        returnTo="/(tabs)/orders"
      />
    );
  }

  function handleOrder(order: Pedido) {
    router.push({ pathname: '/order/[id]', params: { id: String(order.id) } });
  }

  async function handleReorder(order: Pedido) {
    if (reorderingId) return;
    setReorderingId(order.id);
    try {
      const { addedCount, skippedCount } = await reorderPastOrder(order);
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
      setReorderingId(null);
    }
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
          refreshControl={
            <RefreshControl
              refreshing={isRefetching}
              onRefresh={() => void refetch()}
              tintColor={Colors.primary}
              colors={[Colors.primary]}
            />
          }
          renderItem={({ item }) => {
            const estadoColor = getOrderStatusColor(item);
            const isReordering = reorderingId === item.id;
            return (
              <View style={styles.card}>
                <TouchableOpacity
                  onPress={() => handleOrder(item)}
                  activeOpacity={0.7}
                >
                  {/* Cabecera de la Tarjeta */}
                  <View style={styles.cardHeader}>
                    <View style={styles.restaurantInfo}>
                      <Ionicons name="restaurant" size={16} color={Colors.primary || '#111827'} />
                      <Text style={styles.restaurantName} numberOfLines={1}>
                        {item.restaurante_nombre ?? 'UTEQ Cafetería'}
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
                        name="bag-handle-outline"
                        size={15}
                        color={Colors.textMuted}
                      />
                      <Text style={styles.detailText}>
                        Para llevar
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

                <TouchableOpacity
                  style={styles.reorderButton}
                  onPress={() => handleReorder(item)}
                  activeOpacity={0.8}
                  disabled={isReordering}
                  accessibilityLabel={`Pedir de nuevo: ${getOrderTitle(item)}`}
                  accessibilityRole="button"
                >
                  {isReordering ? (
                    <ActivityIndicator size="small" color={Colors.primary} />
                  ) : (
                    <Ionicons name="repeat-outline" size={16} color={Colors.primary} />
                  )}
                  <Text style={styles.reorderButtonText}>
                    {isReordering ? 'Agregando…' : 'Pedir de nuevo'}
                  </Text>
                </TouchableOpacity>
              </View>
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
    fontFamily: FontFamily.heading,
    fontSize: 32,
    color: Colors.text,
    lineHeight: 40,
    paddingTop: 4,
  },
  list: {
    paddingHorizontal: 24,
    paddingBottom: 120,
    paddingTop: 8
  },
  card: {
    backgroundColor: Colors.surface,
    borderRadius: 24,
    padding: 20,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: Colors.border,
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
    color: Colors.text,
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
    color: Colors.textSecondary,
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
    borderTopColor: Colors.borderLight
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6
  },
  detailText: {
    fontSize: 13,
    color: Colors.textMuted,
    fontWeight: '600'
  },
  dot: {
    color: Colors.border,
    fontSize: 14,
    marginHorizontal: 2
  },
  fecha: {
    fontSize: 13,
    color: Colors.textMuted,
    fontWeight: '400'
  },
  reorderButton: {
    marginTop: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    minHeight: 42,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: Colors.primary,
  },
  reorderButtonText: {
    fontSize: 13,
    fontWeight: '700',
    color: Colors.primary,
  },
  // Estilos exclusivos del estado de carga profesional
  skeletonCard: {
    backgroundColor: Colors.surface,
    borderRadius: 24,
    padding: 20,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: Colors.border,
  },
  skeletonDivider: {
    height: 1,
    backgroundColor: Colors.borderLight,
    marginVertical: 16,
  }
});
