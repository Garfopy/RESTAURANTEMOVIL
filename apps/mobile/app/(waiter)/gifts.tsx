import React, { useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useQuery } from '@tanstack/react-query';
import {
  claimWaiterGift,
  deliverWaiterGift,
  getWaiterBranches,
  getWaiterGifts,
  releaseWaiterGift,
  type WaiterGift,
} from '../../services/waiter.service';
import { getApiError } from '../../services/api';

export default function WaiterGiftsScreen() {
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);
  const [updatingId, setUpdatingId] = useState<number | null>(null);
  const branchesQuery = useQuery({ queryKey: ['waiter', 'branches'], queryFn: getWaiterBranches });
  const branches = branchesQuery.data ?? [];
  const selectedBranch = useMemo(
    () => branches.find((branch) => branch.id === selectedBranchId) ?? branches[0] ?? null,
    [branches, selectedBranchId]
  );

  useEffect(() => {
    if (!selectedBranchId && branches.length > 0) setSelectedBranchId(branches[0].id);
  }, [branches, selectedBranchId]);

  const giftsQuery = useQuery({
    queryKey: ['waiter', 'gifts', selectedBranch?.id],
    queryFn: () => getWaiterGifts(selectedBranch!.id),
    enabled: Boolean(selectedBranch?.id),
    refetchInterval: 10_000,
  });
  const gifts = giftsQuery.data?.active ?? [];
  const claimed = gifts.filter((gift) => gift.status === 'reclamado').length;
  const waiting = gifts.filter((gift) => gift.status === 'listo').length;

  async function updateGift(gift: WaiterGift, action: 'claim' | 'release' | 'deliver') {
    if (!selectedBranch?.id) return;
    setUpdatingId(gift.id);
    try {
      if (action === 'claim') await claimWaiterGift(gift.id, selectedBranch.id);
      if (action === 'release') await releaseWaiterGift(gift.id, selectedBranch.id);
      if (action === 'deliver') await deliverWaiterGift(gift.id, selectedBranch.id);
      await giftsQuery.refetch();
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
          <Text style={styles.title}>Regalos</Text>
          <Text style={styles.subtitle}>{gifts.length} activos - {waiting} por reclamar</Text>
        </View>
        <TouchableOpacity style={styles.iconButton} onPress={() => giftsQuery.refetch()} activeOpacity={0.82}>
          {giftsQuery.isRefetching ? <ActivityIndicator size="small" color="#111827" /> : <Ionicons name="refresh" size={20} color="#111827" />}
        </TouchableOpacity>
      </View>

      {branches.length > 1 ? (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.branchRow}>
          {branches.map((branch) => {
            const active = branch.id === selectedBranch?.id;
            return (
              <TouchableOpacity key={branch.id} style={[styles.branchChip, active && styles.branchChipActive]} onPress={() => setSelectedBranchId(branch.id)} activeOpacity={0.86}>
                <Text style={[styles.branchText, active && styles.branchTextActive]} numberOfLines={1}>{branch.nombre}</Text>
              </TouchableOpacity>
            );
          })}
        </ScrollView>
      ) : null}

      <View style={styles.opsPanel}>
        <View style={styles.opsIcon}>
          <Ionicons name="gift-outline" size={22} color="#111827" />
        </View>
        <View style={styles.opsCopy}>
          <Text style={styles.opsTitle}>Entregas sociales</Text>
          <Text style={styles.opsText}>{claimed} reclamados - {waiting} esperando mesero</Text>
        </View>
      </View>

      <FlatList
        data={gifts}
        keyExtractor={(item) => String(item.id)}
        refreshControl={<RefreshControl refreshing={giftsQuery.isRefetching} onRefresh={() => giftsQuery.refetch()} />}
        contentContainerStyle={styles.listContent}
        ListEmptyComponent={
          <View style={styles.empty}>
            <Ionicons name="gift-outline" size={30} color="#94A3B8" />
            <Text style={styles.emptyTitle}>Sin regalos pendientes</Text>
          </View>
        }
        renderItem={({ item }) => {
          const isClaimed = item.status === 'reclamado';
          return (
            <View style={styles.card}>
              <View style={styles.cardTop}>
                {item.gift_image ? (
                  <Image source={{ uri: item.gift_image }} style={styles.image} />
                ) : (
                  <View style={styles.imageFallback}>
                    <Ionicons name="gift" size={22} color="#7C3AED" />
                  </View>
                )}
                <View style={styles.copy}>
                  <View style={styles.titleRow}>
                    <Text style={styles.name} numberOfLines={1}>{item.gift_name}</Text>
                    <View style={[styles.statusPill, isClaimed && styles.statusPillClaimed]}>
                      <Text style={[styles.statusText, isClaimed && styles.statusTextClaimed]}>{isClaimed ? 'Tomado' : 'Listo'}</Text>
                    </View>
                  </View>
                  <Text style={styles.meta} numberOfLines={1}>Mesa {item.recipient_table ?? item.table_id} - de {item.sender_name}</Text>
                  <Text style={styles.status} numberOfLines={1}>{isClaimed ? `Reclamado por ${item.claimed_by_name || 'mesero'}` : 'Listo para reclamar'}</Text>
                </View>
              </View>
              <View style={styles.actions}>
                {!isClaimed ? (
                  <GiftButton label="Reclamar" loading={updatingId === item.id} onPress={() => updateGift(item, 'claim')} />
                ) : (
                  <>
                    <GiftButton label="Soltar" variant="secondary" loading={updatingId === item.id} onPress={() => updateGift(item, 'release')} />
                    <GiftButton label="Entregar" loading={updatingId === item.id} onPress={() => updateGift(item, 'deliver')} />
                  </>
                )}
              </View>
            </View>
          );
        }}
      />
    </SafeAreaView>
  );
}

function GiftButton({ label, loading, onPress, variant }: { label: string; loading: boolean; onPress: () => void; variant?: 'secondary' }) {
  return (
    <TouchableOpacity style={[styles.button, variant === 'secondary' && styles.buttonSecondary]} onPress={onPress} disabled={loading} activeOpacity={0.86}>
      {loading ? <ActivityIndicator color={variant === 'secondary' ? '#111827' : '#FFFFFF'} /> : <Text style={[styles.buttonText, variant === 'secondary' && styles.buttonTextSecondary]}>{label}</Text>}
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#F5F6FA' },
  header: { padding: 20, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  kicker: { color: '#64748B', fontSize: 12, fontWeight: '900', textTransform: 'uppercase' },
  title: { color: '#111827', fontSize: 36, fontWeight: '900' },
  subtitle: { color: '#64748B', fontSize: 15, fontWeight: '700' },
  iconButton: { width: 54, height: 54, borderRadius: 16, backgroundColor: '#FFFFFF', alignItems: 'center', justifyContent: 'center' },
  branchRow: { paddingHorizontal: 20, gap: 10, paddingBottom: 14 },
  branchChip: { minHeight: 50, paddingHorizontal: 18, borderRadius: 16, backgroundColor: '#FFFFFF', justifyContent: 'center' },
  branchChipActive: { backgroundColor: '#111827' },
  branchText: { color: '#111827', fontWeight: '800', fontSize: 15 },
  branchTextActive: { color: '#FFFFFF' },
  opsPanel: {
    marginHorizontal: 20,
    marginBottom: 12,
    borderRadius: 18,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E8EBF0',
    padding: 16,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  opsIcon: { width: 54, height: 54, borderRadius: 16, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },
  opsCopy: { flex: 1 },
  opsTitle: { color: '#111827', fontSize: 20, fontWeight: '900' },
  opsText: { color: '#64748B', fontWeight: '800', fontSize: 15, marginTop: 3 },
  listContent: { padding: 20, paddingTop: 8, paddingBottom: 124, gap: 14 },
  card: { backgroundColor: '#FFFFFF', borderRadius: 18, padding: 16, gap: 16, borderWidth: 1, borderColor: '#E8EBF0' },
  cardTop: { flexDirection: 'row', gap: 14 },
  image: { width: 78, height: 78, borderRadius: 16, backgroundColor: '#F1F5F9' },
  imageFallback: { width: 78, height: 78, borderRadius: 16, backgroundColor: '#F5F3FF', alignItems: 'center', justifyContent: 'center' },
  copy: { flex: 1, gap: 5 },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  name: { flex: 1, color: '#111827', fontSize: 22, fontWeight: '900' },
  meta: { color: '#64748B', fontWeight: '700', fontSize: 15 },
  status: { color: '#7C3AED', fontWeight: '900', fontSize: 15 },
  statusPill: { borderRadius: 14, backgroundColor: '#F5F3FF', paddingHorizontal: 10, paddingVertical: 7 },
  statusPillClaimed: { backgroundColor: '#ECFDF5' },
  statusText: { color: '#7C3AED', fontSize: 13, fontWeight: '900' },
  statusTextClaimed: { color: '#047857' },
  actions: { flexDirection: 'row', gap: 10 },
  button: { flex: 1, height: 58, borderRadius: 18, backgroundColor: '#111827', alignItems: 'center', justifyContent: 'center' },
  buttonSecondary: { backgroundColor: '#EEF2F7' },
  buttonText: { color: '#FFFFFF', fontWeight: '900', fontSize: 16 },
  buttonTextSecondary: { color: '#111827' },
  empty: { alignItems: 'center', marginTop: 80, gap: 8 },
  emptyTitle: { color: '#64748B', fontWeight: '900' },
});
