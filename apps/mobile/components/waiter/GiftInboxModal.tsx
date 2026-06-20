import React, { useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Modal,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { getApiError } from '../../services/api';
import {
  claimWaiterGift,
  deliverWaiterGift,
  releaseWaiterGift,
  type WaiterGift,
  type WaiterGiftInbox,
} from '../../services/waiter.service';

type Filter = 'pending' | 'mine' | 'history';

type Props = {
  visible: boolean;
  restaurantId: number;
  inbox: WaiterGiftInbox;
  onClose: () => void;
  onChanged: () => Promise<unknown> | void;
};

const FILTERS: Array<[Filter, string]> = [
  ['pending', 'Pendientes'],
  ['mine', 'Mis entregas'],
  ['history', 'Historial'],
];

function money(value: number): string {
  return `$${value.toFixed(2)}`;
}

function elapsed(value?: string | null): string {
  if (!value) return 'Ahora';
  const date = new Date(value.replace(' ', 'T'));
  const minutes = Math.max(0, Math.floor((Date.now() - date.getTime()) / 60000));
  if (minutes < 1) return 'Ahora';
  if (minutes < 60) return `Hace ${minutes} min`;
  const hours = Math.floor(minutes / 60);
  return hours < 24 ? `Hace ${hours} h` : `Hace ${Math.floor(hours / 24)} d`;
}

export function GiftInboxModal({ visible, restaurantId, inbox, onClose, onChanged }: Props) {
  const [filter, setFilter] = useState<Filter>('pending');
  const [busyId, setBusyId] = useState<number | null>(null);

  const gifts = useMemo(() => {
    if (filter === 'history') return inbox.history;
    if (filter === 'mine') return inbox.active.filter((gift) => gift.claimed_by_me);
    return inbox.active.filter((gift) => gift.status === 'listo' || !gift.claimed_by_me);
  }, [filter, inbox]);

  async function runAction(gift: WaiterGift, action: 'claim' | 'release' | 'deliver') {
    try {
      setBusyId(gift.id);
      if (action === 'claim') await claimWaiterGift(gift.id, restaurantId);
      if (action === 'release') await releaseWaiterGift(gift.id, restaurantId);
      if (action === 'deliver') await deliverWaiterGift(gift.id, restaurantId);
      await onChanged();
    } catch (error) {
      Alert.alert('Regalos', getApiError(error));
      await onChanged();
    } finally {
      setBusyId(null);
    }
  }

  function confirmDelivery(gift: WaiterGift) {
    Alert.alert(
      'Confirmar entrega',
      `Confirma que entregaste ${gift.gift_name} a ${gift.recipient_name} en Mesa ${gift.recipient_table ?? gift.table_id}.`,
      [
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Entregado', onPress: () => { void runAction(gift, 'deliver'); } },
      ]
    );
  }

  function renderGift(gift: WaiterGift) {
    const busy = busyId === gift.id;
    const claimedByOther = gift.status === 'reclamado' && !gift.claimed_by_me;
    return (
      <View key={gift.id} style={styles.card}>
        <View style={styles.cardTop}>
          {gift.gift_image ? (
            <Image source={{ uri: gift.gift_image }} style={styles.image} contentFit="cover" transition={150} />
          ) : (
            <View style={styles.imageFallback}><Ionicons name="gift" size={26} color="#BE123C" /></View>
          )}
          <View style={styles.cardCopy}>
            <View style={styles.nameRow}>
              <Text style={styles.giftName} numberOfLines={1}>{gift.gift_name}</Text>
              <Text style={styles.price}>{money(gift.gift_price)}</Text>
            </View>
            <Text style={styles.folio}>{gift.folio ?? `Regalo #${gift.id}`} · {elapsed(gift.created_at)}</Text>
            <View style={styles.destination}>
              <Ionicons name="location" size={15} color="#BE123C" />
              <Text style={styles.destinationText}>Mesa {gift.recipient_table ?? gift.table_id}</Text>
            </View>
          </View>
        </View>

        <View style={styles.peopleBox}>
          <Text style={styles.peopleText}><Text style={styles.peopleLabel}>De:</Text> {gift.sender_name}</Text>
          <Ionicons name="arrow-forward" size={15} color="#94A3B8" />
          <Text style={styles.peopleText} numberOfLines={1}><Text style={styles.peopleLabel}>Para:</Text> {gift.recipient_name}</Text>
        </View>

        {gift.status === 'entregado' ? (
          <View style={styles.deliveredBadge}>
            <Ionicons name="checkmark-circle" size={17} color="#15803D" />
            <Text style={styles.deliveredText}>Entregado por {gift.delivered_by_name ?? 'el equipo'} · {elapsed(gift.delivered_at)}</Text>
          </View>
        ) : claimedByOther ? (
          <View style={styles.claimedBadge}>
            <Ionicons name="person-circle-outline" size={17} color="#475569" />
            <Text style={styles.claimedText}>En camino con {gift.claimed_by_name ?? 'otro mesero'}</Text>
          </View>
        ) : gift.claimed_by_me ? (
          <View style={styles.actions}>
            <TouchableOpacity style={styles.releaseButton} onPress={() => runAction(gift, 'release')} disabled={busy}>
              <Text style={styles.releaseText}>Liberar</Text>
            </TouchableOpacity>
            <TouchableOpacity style={styles.deliverButton} onPress={() => confirmDelivery(gift)} disabled={busy}>
              {busy ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.deliverText}>Marcar entregado</Text>}
            </TouchableOpacity>
          </View>
        ) : (
          <TouchableOpacity style={styles.claimButton} onPress={() => runAction(gift, 'claim')} disabled={busy}>
            {busy ? <ActivityIndicator color="#FFFFFF" /> : (
              <><Ionicons name="hand-left-outline" size={18} color="#FFFFFF" /><Text style={styles.claimText}>Reclamar entrega</Text></>
            )}
          </TouchableOpacity>
        )}
      </View>
    );
  }

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose}>
      <SafeAreaView style={styles.safe}>
        <View style={styles.header}>
          <TouchableOpacity style={styles.closeButton} onPress={onClose}>
            <Ionicons name="close" size={23} color="#111827" />
          </TouchableOpacity>
          <View style={styles.headerCopy}>
            <Text style={styles.title}>Entregas de regalos</Text>
            <Text style={styles.subtitle}>{inbox.active.length} activas · {inbox.history.length} entregadas hoy</Text>
          </View>
          <View style={styles.badge}><Text style={styles.badgeText}>{inbox.pending_count}</Text></View>
        </View>

        <View style={styles.filters}>
          {FILTERS.map(([value, label]) => (
            <TouchableOpacity key={value} style={[styles.filter, filter === value && styles.filterActive]} onPress={() => setFilter(value)}>
              <Text style={[styles.filterText, filter === value && styles.filterTextActive]}>{label}</Text>
            </TouchableOpacity>
          ))}
        </View>

        <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
          {gifts.length ? gifts.map(renderGift) : (
            <View style={styles.empty}>
              <Ionicons name={filter === 'history' ? 'checkmark-done-outline' : 'gift-outline'} size={46} color="#CBD5E1" />
              <Text style={styles.emptyTitle}>{filter === 'history' ? 'Sin entregas hoy' : 'Todo bajo control'}</Text>
              <Text style={styles.emptyText}>No hay regalos en esta sección.</Text>
            </View>
          )}
        </ScrollView>
      </SafeAreaView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#F5F6F8' },
  header: { padding: 16, flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: '#FFFFFF', borderBottomWidth: 1, borderBottomColor: '#E5E7EB' },
  closeButton: { width: 42, height: 42, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: '#F1F5F9' },
  headerCopy: { flex: 1 },
  title: { color: '#111827', fontSize: 20, fontWeight: '900' },
  subtitle: { marginTop: 3, color: '#64748B', fontSize: 12 },
  badge: { minWidth: 34, height: 34, borderRadius: 17, paddingHorizontal: 9, alignItems: 'center', justifyContent: 'center', backgroundColor: '#FFE4E6' },
  badgeText: { color: '#BE123C', fontWeight: '900' },
  filters: { padding: 12, flexDirection: 'row', gap: 8, backgroundColor: '#FFFFFF' },
  filter: { flex: 1, paddingVertical: 10, borderRadius: 12, alignItems: 'center', backgroundColor: '#F1F5F9' },
  filterActive: { backgroundColor: '#111827' },
  filterText: { color: '#64748B', fontSize: 12, fontWeight: '800' },
  filterTextActive: { color: '#FFFFFF' },
  content: { padding: 14, paddingBottom: 36, gap: 13 },
  card: { padding: 14, borderRadius: 20, borderWidth: 1, borderColor: '#E5E7EB', backgroundColor: '#FFFFFF' },
  cardTop: { flexDirection: 'row', gap: 12 },
  image: { width: 64, height: 64, borderRadius: 16, backgroundColor: '#F1F5F9' },
  imageFallback: { width: 64, height: 64, borderRadius: 16, alignItems: 'center', justifyContent: 'center', backgroundColor: '#FFF1F2' },
  cardCopy: { flex: 1, minWidth: 0 },
  nameRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  giftName: { flex: 1, color: '#111827', fontSize: 17, fontWeight: '900' },
  price: { color: '#111827', fontSize: 15, fontWeight: '900' },
  folio: { marginTop: 4, color: '#64748B', fontSize: 11, fontWeight: '600' },
  destination: { marginTop: 8, flexDirection: 'row', alignItems: 'center', gap: 4 },
  destinationText: { color: '#BE123C', fontSize: 14, fontWeight: '900' },
  peopleBox: { marginTop: 12, padding: 10, borderRadius: 12, flexDirection: 'row', alignItems: 'center', gap: 7, backgroundColor: '#F8FAFC' },
  peopleText: { flex: 1, color: '#475569', fontSize: 12 },
  peopleLabel: { color: '#1F2937', fontWeight: '800' },
  actions: { marginTop: 12, flexDirection: 'row', gap: 9 },
  releaseButton: { width: 90, height: 46, borderRadius: 13, alignItems: 'center', justifyContent: 'center', backgroundColor: '#F1F5F9' },
  releaseText: { color: '#475569', fontWeight: '800' },
  deliverButton: { flex: 1, height: 46, borderRadius: 13, alignItems: 'center', justifyContent: 'center', backgroundColor: '#16A34A' },
  deliverText: { color: '#FFFFFF', fontWeight: '900' },
  claimButton: { marginTop: 12, height: 46, borderRadius: 13, flexDirection: 'row', gap: 8, alignItems: 'center', justifyContent: 'center', backgroundColor: '#BE123C' },
  claimText: { color: '#FFFFFF', fontWeight: '900' },
  claimedBadge: { marginTop: 12, padding: 11, borderRadius: 12, flexDirection: 'row', gap: 7, alignItems: 'center', backgroundColor: '#F1F5F9' },
  claimedText: { color: '#475569', fontSize: 12, fontWeight: '700' },
  deliveredBadge: { marginTop: 12, padding: 11, borderRadius: 12, flexDirection: 'row', gap: 7, alignItems: 'center', backgroundColor: '#F0FDF4' },
  deliveredText: { flex: 1, color: '#15803D', fontSize: 12, fontWeight: '700' },
  empty: { paddingVertical: 80, alignItems: 'center' },
  emptyTitle: { marginTop: 14, color: '#334155', fontSize: 18, fontWeight: '900' },
  emptyText: { marginTop: 5, color: '#94A3B8', fontSize: 13 },
});
