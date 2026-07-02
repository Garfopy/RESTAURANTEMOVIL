import React, { useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Modal,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import {
  claimWaiterIncomingOrder,
  deliverWaiterIncomingOrder,
  getWaiterBranches,
  getWaiterIncomingOrders,
  type WaiterIncomingOrder,
} from '../../services/waiter.service';
import { getApiError } from '../../services/api';

export default function WaiterOrdersScreen() {
  const router = useRouter();
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);
  const [updatingId, setUpdatingId] = useState<number | null>(null);
  const [selectedOrder, setSelectedOrder] = useState<WaiterIncomingOrder | null>(null);
  const branchesQuery = useQuery({ queryKey: ['waiter', 'branches'], queryFn: getWaiterBranches });
  const branches = branchesQuery.data ?? [];
  const selectedBranch = useMemo(
    () => branches.find((branch) => branch.id === selectedBranchId) ?? branches[0] ?? null,
    [branches, selectedBranchId]
  );

  useEffect(() => {
    if (!selectedBranchId && branches.length > 0) setSelectedBranchId(branches[0].id);
  }, [branches, selectedBranchId]);

  const ordersQuery = useQuery({
    queryKey: ['waiter', 'incoming-orders', selectedBranch?.id],
    queryFn: () => getWaiterIncomingOrders(selectedBranch!.id),
    enabled: Boolean(selectedBranch?.id),
    refetchInterval: 8_000,
  });
  const orders = ordersQuery.data ?? [];
  const ready = orders.filter((order) => order.is_ready).length;
  const claimed = orders.filter((order) => order.claimed_by_me).length;
  const unassigned = Math.max(0, orders.length - claimed);

  async function updateOrder(order: WaiterIncomingOrder) {
    if (!selectedBranch?.id) return;
    setUpdatingId(order.id);
    try {
      if (!order.claimed_by_me) {
        await claimWaiterIncomingOrder(order.id, selectedBranch.id);
        if (order.is_ready) {
          await deliverWaiterIncomingOrder(order.id, selectedBranch.id);
          setSelectedOrder(null);
        }
      } else if (order.is_ready) {
        await deliverWaiterIncomingOrder(order.id, selectedBranch.id);
        setSelectedOrder(null);
      } else {
        router.push({
          pathname: '/(waiter)/table/[id]',
          params: { id: String(order.table_id), restaurantId: String(selectedBranch.id) },
        });
      }
      await ordersQuery.refetch();
    } catch (error) {
      Alert.alert('No se pudo actualizar', getApiError(error));
    } finally {
      setUpdatingId(null);
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <View>
          <Text style={styles.kicker}>Mesero</Text>
          <Text style={styles.title}>Pedidos</Text>
          <Text style={styles.subtitle}>{orders.length} activos - {ready} listos</Text>
        </View>
        <TouchableOpacity style={styles.iconButton} onPress={() => ordersQuery.refetch()} activeOpacity={0.82}>
          {ordersQuery.isRefetching ? <ActivityIndicator size="small" color="#111827" /> : <Ionicons name="refresh" size={20} color="#111827" />}
        </TouchableOpacity>
      </View>

      {branches.length > 1 ? (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.branchRow}>
          {branches.map((branch) => {
            const active = branch.id === selectedBranch?.id;
            return (
              <TouchableOpacity
                key={branch.id}
                style={[styles.branchChip, active && styles.branchChipActive]}
                onPress={() => setSelectedBranchId(branch.id)}
                activeOpacity={0.86}
              >
                <Text style={[styles.branchText, active && styles.branchTextActive]} numberOfLines={1}>{branch.nombre}</Text>
              </TouchableOpacity>
            );
          })}
        </ScrollView>
      ) : null}

      <View style={styles.opsPanel}>
        <View style={styles.opsIcon}>
          <Ionicons name="receipt-outline" size={22} color="#111827" />
        </View>
        <View style={styles.opsCopy}>
          <Text style={styles.opsTitle}>Comandas desde cliente</Text>
          <Text style={styles.opsText}>{claimed} tomadas - {unassigned} sin asignar</Text>
        </View>
      </View>

      <FlatList
        data={orders}
        keyExtractor={(item) => String(item.id)}
        refreshControl={<RefreshControl refreshing={ordersQuery.isRefetching} onRefresh={() => ordersQuery.refetch()} />}
        contentContainerStyle={styles.listContent}
        ListEmptyComponent={
          <View style={styles.empty}>
            <Ionicons name="receipt-outline" size={30} color="#94A3B8" />
            <Text style={styles.emptyTitle}>Sin pedidos pendientes</Text>
          </View>
        }
        renderItem={({ item }) => (
          <TouchableOpacity style={styles.card} activeOpacity={0.88} onPress={() => setSelectedOrder(item)}>
            <View style={styles.cardTop}>
              <View style={[styles.cardIcon, item.is_ready && styles.cardIconReady]}>
                <Ionicons name={item.is_ready ? 'checkmark-circle-outline' : 'restaurant-outline'} size={20} color={item.is_ready ? '#047857' : '#92400E'} />
              </View>
              <View style={styles.cardCopy}>
                <Text style={styles.cardTitle}>{item.table_label}</Text>
                <Text style={styles.cardText} numberOfLines={1}>{item.cliente_nombre || 'Cliente app'} - {item.items_count} productos</Text>
                <Text style={[styles.status, item.is_ready && styles.statusReady]}>{item.is_ready ? 'Listo para entregar' : 'En cocina'}</Text>
              </View>
              <Text style={styles.total}>${Number(item.total || 0).toFixed(2)}</Text>
            </View>
            <View style={styles.tapHint}>
              <Text style={styles.tapHintText}>Ver detalle</Text>
              <Ionicons name="chevron-forward" size={17} color="#64748B" />
            </View>
          </TouchableOpacity>
        )}
      />

      <OrderDetailModal
        order={selectedOrder}
        loading={selectedOrder ? updatingId === selectedOrder.id : false}
        onClose={() => setSelectedOrder(null)}
        onPrimary={(order) => updateOrder(order)}
      />
    </SafeAreaView>
  );
}

function OrderDetailModal({
  order,
  loading,
  onClose,
  onPrimary,
}: {
  order: WaiterIncomingOrder | null;
  loading: boolean;
  onClose: () => void;
  onPrimary: (order: WaiterIncomingOrder) => void;
}) {
  if (!order) return null;

  const items = order.items ?? [];
  const primaryLabel = !order.claimed_by_me
    ? order.is_ready ? 'Tomar y entregar' : 'Tomar pedido'
    : order.is_ready ? 'Marcar entregado' : 'Abrir mesa';

  return (
    <Modal visible transparent animationType="fade" onRequestClose={onClose}>
      <Pressable style={styles.modalOverlay} onPress={onClose}>
        <Pressable style={styles.detailSheet}>
          <View style={styles.detailHeader}>
            <View>
              <Text style={styles.detailKicker}>Detalle de comanda</Text>
              <Text style={styles.detailTitle}>{order.table_label}</Text>
            </View>
            <TouchableOpacity style={styles.detailClose} onPress={onClose}>
              <Ionicons name="close" size={20} color="#111827" />
            </TouchableOpacity>
          </View>

          <View style={styles.detailMetaGrid}>
            <DetailMeta icon="person-outline" label="Comensal" value={order.cliente_nombre || 'Cliente app'} />
            <DetailMeta icon="receipt-outline" label="Folio" value={order.folio || `#${order.id}`} />
            <DetailMeta icon="restaurant-outline" label="Estado" value={order.is_ready ? 'Listo' : 'En cocina'} />
            <DetailMeta icon="cash-outline" label="Total" value={`$${Number(order.total || 0).toFixed(2)}`} />
          </View>

          <Text style={styles.productsTitle}>Productos</Text>
          <View style={styles.productsList}>
            {items.length > 0 ? items.map((item) => (
              <View key={item.id} style={styles.productRow}>
                <View style={styles.productQty}>
                  <Text style={styles.productQtyText}>{item.cantidad}</Text>
                </View>
                <View style={styles.productCopy}>
                  <Text style={styles.productName} numberOfLines={2}>{item.nombre}</Text>
                  {item.notas ? <Text style={styles.productNotes} numberOfLines={2}>{item.notas}</Text> : null}
                </View>
                <Text style={styles.productTotal}>${Number(item.subtotal || 0).toFixed(2)}</Text>
              </View>
            )) : (
              <Text style={styles.noProducts}>Sin productos registrados.</Text>
            )}
          </View>

          <TouchableOpacity style={styles.detailPrimary} onPress={() => onPrimary(order)} disabled={loading}>
            {loading ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.detailPrimaryText}>{primaryLabel}</Text>}
          </TouchableOpacity>
        </Pressable>
      </Pressable>
    </Modal>
  );
}

function DetailMeta({ icon, label, value }: { icon: keyof typeof Ionicons.glyphMap; label: string; value: string }) {
  return (
    <View style={styles.detailMeta}>
      <Ionicons name={icon} size={16} color="#64748B" />
      <Text style={styles.detailMetaLabel}>{label}</Text>
      <Text style={styles.detailMetaValue} numberOfLines={1}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#F5F6FA' },
  header: { padding: 20, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  kicker: { color: '#64748B', fontSize: 12, fontWeight: '900', textTransform: 'uppercase' },
  title: { color: '#111827', fontSize: 36, fontWeight: '900' },
  subtitle: { color: '#64748B', fontSize: 15, fontWeight: '700' },
  iconButton: { width: 44, height: 44, borderRadius: 8, backgroundColor: '#FFFFFF', alignItems: 'center', justifyContent: 'center' },
  branchRow: { paddingHorizontal: 20, gap: 10, paddingBottom: 12 },
  branchChip: { height: 40, paddingHorizontal: 14, borderRadius: 8, backgroundColor: '#FFFFFF', justifyContent: 'center' },
  branchChipActive: { backgroundColor: '#111827' },
  branchText: { color: '#111827', fontWeight: '800' },
  branchTextActive: { color: '#FFFFFF' },
  opsPanel: {
    marginHorizontal: 20,
    marginBottom: 12,
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E8EBF0',
    padding: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  opsIcon: { width: 44, height: 44, borderRadius: 8, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },
  opsCopy: { flex: 1 },
  opsTitle: { color: '#111827', fontSize: 17, fontWeight: '900' },
  opsText: { color: '#64748B', fontWeight: '800', marginTop: 2 },
  listContent: { padding: 20, paddingTop: 8, paddingBottom: 108, gap: 12 },
  card: { backgroundColor: '#FFFFFF', borderRadius: 8, padding: 14, gap: 14, borderWidth: 1, borderColor: '#E8EBF0' },
  cardTop: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  cardIcon: { width: 42, height: 42, borderRadius: 8, backgroundColor: '#FFFBEB', alignItems: 'center', justifyContent: 'center' },
  cardIconReady: { backgroundColor: '#ECFDF5' },
  cardCopy: { flex: 1 },
  cardTitle: { color: '#111827', fontSize: 18, fontWeight: '900' },
  cardText: { color: '#64748B', fontWeight: '700', marginTop: 2 },
  status: { color: '#92400E', fontSize: 12, fontWeight: '900', marginTop: 4 },
  statusReady: { color: '#047857' },
  total: { color: '#111827', fontWeight: '900' },
  actionButton: { height: 46, borderRadius: 8, backgroundColor: '#111827', alignItems: 'center', justifyContent: 'center' },
  actionText: { color: '#FFFFFF', fontWeight: '900' },
  tapHint: {
    height: 36,
    borderRadius: 8,
    backgroundColor: '#F8FAFC',
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  tapHintText: { color: '#64748B', fontWeight: '900' },
  empty: { alignItems: 'center', marginTop: 80, gap: 8 },
  emptyTitle: { color: '#64748B', fontWeight: '900' },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.38)',
    justifyContent: 'flex-end',
  },
  detailSheet: {
    backgroundColor: '#FFFFFF',
    borderTopLeftRadius: 8,
    borderTopRightRadius: 8,
    padding: 20,
    paddingBottom: 28,
    gap: 16,
  },
  detailHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: 14,
  },
  detailKicker: { color: '#64748B', fontSize: 12, fontWeight: '900', textTransform: 'uppercase' },
  detailTitle: { color: '#111827', fontSize: 28, fontWeight: '900', marginTop: 2 },
  detailClose: {
    width: 40,
    height: 40,
    borderRadius: 8,
    backgroundColor: '#F1F5F9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  detailMetaGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  detailMeta: {
    width: '48%',
    minHeight: 78,
    borderRadius: 8,
    backgroundColor: '#F8FAFC',
    padding: 10,
    justifyContent: 'space-between',
  },
  detailMetaLabel: { color: '#64748B', fontSize: 11, fontWeight: '900', textTransform: 'uppercase' },
  detailMetaValue: { color: '#111827', fontSize: 15, fontWeight: '900' },
  productsTitle: { color: '#111827', fontSize: 18, fontWeight: '900' },
  productsList: { gap: 10 },
  productRow: {
    minHeight: 62,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#E8EBF0',
    padding: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  productQty: {
    width: 34,
    height: 34,
    borderRadius: 8,
    backgroundColor: '#111827',
    alignItems: 'center',
    justifyContent: 'center',
  },
  productQtyText: { color: '#FFFFFF', fontWeight: '900' },
  productCopy: { flex: 1 },
  productName: { color: '#111827', fontSize: 15, fontWeight: '900' },
  productNotes: { color: '#64748B', fontWeight: '700', marginTop: 2 },
  productTotal: { color: '#111827', fontWeight: '900' },
  noProducts: { color: '#64748B', fontWeight: '800' },
  detailPrimary: {
    height: 50,
    borderRadius: 8,
    backgroundColor: '#111827',
    alignItems: 'center',
    justifyContent: 'center',
  },
  detailPrimaryText: { color: '#FFFFFF', fontWeight: '900', fontSize: 16 },
});
