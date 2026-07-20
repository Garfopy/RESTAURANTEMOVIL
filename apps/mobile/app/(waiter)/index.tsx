import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Keyboard,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import * as Haptics from 'expo-haptics';
import { getApiError } from '../../services/api';
import { logout as unregisterSessionPush } from '../../services/auth.service';
import {
  claimWaiterTable,
  getWaiterBranches,
  getWaiterGifts,
  getWaiterIncomingOrders,
  getWaiterTables,
  type WaiterIncomingOrder,
  type WaiterTable,
} from '../../services/waiter.service';
import { useUserStore } from '../../store/user.store';
import { Colors } from '../../theme';
import { useToast } from '../../context/ToastContext';
import { GiftInboxModal } from '../../components/waiter/GiftInboxModal';

type StatusIcon = keyof typeof Ionicons.glyphMap;
type TableFilter = 'all' | 'mine' | 'open' | 'free' | 'support' | 'gifts';

const STATUS_LABEL: Record<WaiterTable['status'], string> = {
  libre: 'Libre',
  mia: 'Mi mesa',
  cuenta_abierta: 'Cuenta abierta',
  ocupada_por_otro: 'Apoyo',
};

const STATUS_COLOR: Record<WaiterTable['status'], string> = {
  libre: '#059669',
  mia: '#2563EB',
  cuenta_abierta: '#B45309',
  ocupada_por_otro: '#64748B',
};

const STATUS_BG: Record<WaiterTable['status'], string> = {
  libre: '#ECFDF5',
  mia: '#EFF6FF',
  cuenta_abierta: '#FFFBEB',
  ocupada_por_otro: '#F1F5F9',
};

const STATUS_ICON: Record<WaiterTable['status'], StatusIcon> = {
  libre: 'checkmark-circle-outline',
  mia: 'person-circle-outline',
  cuenta_abierta: 'receipt-outline',
  ocupada_por_otro: 'people-outline',
};

function normalizeSearchText(value?: string | number | null): string {
  return String(value ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim();
}

function money(value: unknown): string {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? `$${parsed.toFixed(2)}` : '$0.00';
}

export default function WaiterHomeScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const user = useUserStore((state) => state.user);
  const logout = useUserStore((state) => state.logout);
  const toast = useToast();
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);
  const [searchText, setSearchText] = useState('');
  const [claimingTable, setClaimingTable] = useState<WaiterTable | null>(null);
  const [customerName, setCustomerName] = useState('');
  const [claiming, setClaiming] = useState(false);
  const [giftsVisible, setGiftsVisible] = useState(false);
  const [activeFilter, setActiveFilter] = useState<TableFilter>('all');
  const [tableGrouping, setTableGrouping] = useState<'status' | 'zone'>('status');
  const seenGiftIds = useRef<{ branchId: number | null; ids: Set<number> }>({ branchId: null, ids: new Set() });
  const seenClientOrderIds = useRef<{ branchId: number | null; ids: Set<number> }>({ branchId: null, ids: new Set() });

  const branchesQuery = useQuery({
    queryKey: ['waiter', 'branches'],
    queryFn: getWaiterBranches,
  });

  const branches = branchesQuery.data ?? [];
  const selectedBranch = useMemo(
    () => branches.find((branch) => branch.id === selectedBranchId) ?? branches[0] ?? null,
    [branches, selectedBranchId]
  );

  useEffect(() => {
    if (!selectedBranchId && branches.length > 0) {
      setSelectedBranchId(branches[0].id);
    }
  }, [branches, selectedBranchId]);

  const tablesQuery = useQuery({
    queryKey: ['waiter', 'tables', selectedBranch?.id],
    queryFn: () => getWaiterTables(selectedBranch!.id),
    enabled: Boolean(selectedBranch?.id),
    refetchInterval: 12_000,
    refetchIntervalInBackground: false,
  });

  const giftsQuery = useQuery({
    queryKey: ['waiter', 'gifts', selectedBranch?.id],
    queryFn: () => getWaiterGifts(selectedBranch!.id),
    enabled: Boolean(selectedBranch?.id),
    refetchInterval: 10_000,
    refetchIntervalInBackground: false,
  });
  const incomingOrdersQuery = useQuery({
    queryKey: ['waiter', 'incoming-orders', selectedBranch?.id],
    queryFn: () => getWaiterIncomingOrders(selectedBranch!.id),
    enabled: Boolean(selectedBranch?.id),
    refetchInterval: 8_000,
    refetchIntervalInBackground: false,
  });

  const giftInbox = giftsQuery.data ?? { active: [], history: [], pending_count: 0 };
  const incomingOrders = incomingOrdersQuery.data ?? [];
  const incomingOrderByTable = useMemo(() => incomingOrders.reduce<Record<number, WaiterIncomingOrder>>((map, order) => {
    if (!map[order.table_id]) {
      map[order.table_id] = order;
    }
    return map;
  }, {}), [incomingOrders]);
  const giftCountByTable = useMemo(() => giftInbox.active.reduce<Record<number, number>>((counts, gift) => {
    counts[gift.table_id] = (counts[gift.table_id] ?? 0) + 1;
    return counts;
  }, {}), [giftInbox.active]);
  const pendingGiftCountByTable = useMemo(() => giftInbox.active.reduce<Record<number, number>>((counts, gift) => {
    if (gift.status === 'listo') {
      counts[gift.table_id] = (counts[gift.table_id] ?? 0) + 1;
    }
    return counts;
  }, {}), [giftInbox.active]);

  useEffect(() => {
    if (!selectedBranch?.id || !giftsQuery.data) return;
    const readyIds = new Set(giftsQuery.data.active.filter((gift) => gift.status === 'listo').map((gift) => gift.id));
    if (seenGiftIds.current.branchId !== selectedBranch.id) {
      seenGiftIds.current = { branchId: selectedBranch.id, ids: readyIds };
      return;
    }
    const newIds = [...readyIds].filter((id) => !seenGiftIds.current.ids.has(id));
    seenGiftIds.current.ids = readyIds;
    if (newIds.length > 0) {
      const newest = giftsQuery.data.active.find((gift) => gift.id === newIds[newIds.length - 1]);
      toast.info(
        newIds.length === 1 && newest
          ? `Nuevo regalo para Mesa ${newest.recipient_table ?? newest.table_id}: ${newest.gift_name}`
          : `${newIds.length} regalos nuevos por entregar`,
        { duration: 6000, icon: 'gift' }
      );
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    }
  }, [giftsQuery.data, selectedBranch?.id, toast]);

  useEffect(() => {
    if (!selectedBranch?.id || !incomingOrdersQuery.data) return;
    const ids = new Set(incomingOrdersQuery.data.map((order) => order.id));
    if (seenClientOrderIds.current.branchId !== selectedBranch.id) {
      seenClientOrderIds.current = { branchId: selectedBranch.id, ids };
      return;
    }

    const newIds = [...ids].filter((id) => !seenClientOrderIds.current.ids.has(id));
    seenClientOrderIds.current.ids = ids;
    if (newIds.length > 0) {
      const newest = incomingOrdersQuery.data.find((order) => order.id === newIds[0]);
      toast.info(
        newIds.length === 1 && newest
          ? `Nuevo pedido de cliente en ${newest.table_label}`
          : `${newIds.length} pedidos nuevos de cliente`,
        { duration: 6000, icon: 'restaurant' }
      );
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    }
  }, [incomingOrdersQuery.data, selectedBranch?.id, toast]);

  const tables = tablesQuery.data ?? [];
  const priorityTables = useMemo(() => {
    const statusWeight = (table: WaiterTable) => {
      if (pendingGiftCountByTable[table.id]) return 0;
      if (incomingOrderByTable[table.id]) return 1;
      if (table.status === 'mia' || (table.status === 'cuenta_abierta' && table.mesero_usuario_id === user?.id)) return 1;
      if (table.status === 'ocupada_por_otro' || table.status === 'cuenta_abierta') return 2;
      return 3;
    };

    return [...tables].sort((a, b) => {
      const giftDiff = (pendingGiftCountByTable[b.id] ?? 0) - (pendingGiftCountByTable[a.id] ?? 0);
      if (giftDiff !== 0) return giftDiff;
      const statusDiff = statusWeight(a) - statusWeight(b);
      if (statusDiff !== 0) return statusDiff;
      return String(a.label).localeCompare(String(b.label), 'es', { numeric: true });
    });
  }, [incomingOrderByTable, pendingGiftCountByTable, tables, user?.id]);

  const filteredTables = useMemo(() => {
    const query = normalizeSearchText(searchText);
    if (!query) return priorityTables;

    return priorityTables.filter((table) =>
      [
        table.label,
        table.value,
        table.zona_nombre,
        table.cliente_nombre,
        incomingOrderByTable[table.id]?.cliente_nombre,
        table.mesero_nombre,
        table.estado,
        table.id,
      ].some((value) => normalizeSearchText(value).includes(query))
    );
  }, [incomingOrderByTable, priorityTables, searchText]);

  const myTables = filteredTables.filter(
    (table) =>
      table.status === 'mia' ||
      (table.status === 'cuenta_abierta' && table.mesero_usuario_id === user?.id)
  );
  const supportTables = filteredTables.filter(
    (table) =>
      table.status === 'ocupada_por_otro' ||
      (table.status === 'cuenta_abierta' && table.mesero_usuario_id !== user?.id)
  );
  const openTables = filteredTables.filter((table) => table.cuenta_abierta || Number(table.total || 0) > 0);
  const activeClientTables = filteredTables.filter((table) => Boolean(incomingOrderByTable[table.id]));
  const freeTables = filteredTables.filter((table) => table.status === 'libre' && !incomingOrderByTable[table.id]);
  const giftTables = filteredTables.filter((table) => (giftCountByTable[table.id] ?? 0) > 0);
  const activeTables = activeFilter === 'all'
    ? filteredTables
    : activeFilter === 'mine'
    ? myTables
    : activeFilter === 'open'
      ? [...new Map([...openTables, ...activeClientTables].map((table) => [table.id, table])).values()]
    : activeFilter === 'free'
      ? freeTables
      : activeFilter === 'support'
        ? supportTables
        : giftTables;
  const activeEmptyText = activeFilter === 'all'
    ? 'No hay mesas para mostrar.'
    : activeFilter === 'mine'
    ? 'Todavía no tienes mesas asignadas.'
    : activeFilter === 'open'
      ? 'No hay mesas por cobrar en este momento.'
    : activeFilter === 'free'
      ? 'No hay mesas libres en este momento.'
      : activeFilter === 'support'
        ? 'No hay mesas ocupadas por otros meseros.'
        : 'No hay regalos activos en esta sucursal.';
  const filters: Array<{ key: TableFilter; label: string; count: number; icon: StatusIcon }> = [
    { key: 'all', label: 'Todas', count: filteredTables.length, icon: 'apps-outline' },
    { key: 'mine', label: 'Mis mesas', count: myTables.length, icon: 'person-outline' },
    { key: 'open', label: 'Por cobrar', count: new Set([...openTables, ...activeClientTables].map((table) => table.id)).size, icon: 'receipt-outline' },
    { key: 'free', label: 'Libres', count: freeTables.length, icon: 'grid-outline' },
    { key: 'support', label: 'Apoyo', count: supportTables.length, icon: 'people-outline' },
    { key: 'gifts', label: 'Regalos', count: giftInbox.active.length, icon: 'gift-outline' },
  ];
  const zoneGroups = useMemo(() => {
    const groups = new Map<string, { key: string; title: string; tables: WaiterTable[] }>();

    filteredTables.forEach((table) => {
      const title = table.zona_nombre?.trim() || 'Sin área';
      const key = table.zona_id ? `zone-${table.zona_id}` : `zone-${title}`;
      const group = groups.get(key) ?? { key, title, tables: [] };
      group.tables.push(table);
      groups.set(key, group);
    });

    return Array.from(groups.values()).sort((a, b) => a.title.localeCompare(b.title, 'es'));
  }, [filteredTables]);

  const summary = useMemo(() => {
    const mine = tables.filter(
      (table) =>
        table.status === 'mia' ||
        (table.status === 'cuenta_abierta' && table.mesero_usuario_id === user?.id)
    ).length;
    const support = tables.filter(
      (table) =>
        table.status === 'ocupada_por_otro' ||
        (table.status === 'cuenta_abierta' && table.mesero_usuario_id !== user?.id)
    ).length;
    const free = tables.filter((table) => table.status === 'libre' && !incomingOrderByTable[table.id]).length;
    const total = tables.reduce(
      (sum, table) => sum + Number(table.total || incomingOrderByTable[table.id]?.total || 0),
      0
    );
    return { mine, support, free, total };
  }, [incomingOrderByTable, tables, user?.id]);

  function openTable(table: WaiterTable) {
    if (!selectedBranch) return;

    const incomingOrder = incomingOrderByTable[table.id];
    if (table.status === 'libre' && !incomingOrder) {
      setClaimingTable(table);
      setCustomerName(table.cliente_nombre ?? '');
      return;
    }

    router.push({
      pathname: '/(waiter)/table/[id]',
      params: {
        id: String(table.id),
        restaurantId: String(selectedBranch.id),
        tableLabel: table.label,
        clienteNombre: table.cliente_nombre ?? incomingOrder?.cliente_nombre ?? '',
        meseroNombre: table.mesero_nombre ?? '',
        supportMode: table.mesero_usuario_id && table.mesero_usuario_id !== user?.id ? '1' : '0',
      },
    });
  }

  function renderTableCard(table: WaiterTable) {
    const giftCount = giftCountByTable[table.id] ?? 0;
    const incomingOrder = incomingOrderByTable[table.id];
    const effectiveStatus: WaiterTable['status'] = incomingOrder && table.status === 'libre' ? 'cuenta_abierta' : table.status;
    const effectiveCustomerName = table.cliente_nombre || incomingOrder?.cliente_nombre || 'Sin comensal';
    const effectiveTotal = Number(table.total || incomingOrder?.total || 0);
    return (
      <TouchableOpacity key={table.id} activeOpacity={0.9} style={styles.tableCard} onPress={() => openTable(table)}>
        <View style={styles.tableTopRow}>
          <View style={[styles.statusBadge, { backgroundColor: STATUS_BG[effectiveStatus] }]}>
            <Ionicons name={STATUS_ICON[effectiveStatus]} size={18} color={STATUS_COLOR[effectiveStatus]} />
            <Text style={[styles.statusBadgeText, { color: STATUS_COLOR[effectiveStatus] }]}>
              {incomingOrder ? 'Pedido cliente' : STATUS_LABEL[effectiveStatus]}
            </Text>
          </View>
          {giftCount > 0 ? (
            <View style={styles.tableGiftBadge}>
              <Ionicons name="gift" size={14} color="#BE123C" />
              <Text style={styles.tableGiftBadgeText}>{giftCount}</Text>
            </View>
          ) : null}
          <Ionicons name="chevron-forward" size={24} color="#94A3B8" />
        </View>

        <View style={styles.tableIdentity}>
          <Text style={styles.tableLabel} numberOfLines={1}>{table.label}</Text>
          {table.zona_nombre ? <Text style={styles.zoneName} numberOfLines={1}>{table.zona_nombre}</Text> : null}
        </View>

        <View style={styles.tableMeta}>
          <View style={styles.metaLine}>
            <Ionicons name="person-outline" size={14} color="#64748B" />
            <Text style={styles.metaText} numberOfLines={1}>{effectiveCustomerName}</Text>
          </View>
          <View style={styles.metaLine}>
            <Ionicons name="restaurant-outline" size={14} color="#64748B" />
            <Text style={styles.metaText} numberOfLines={1}>{table.mesero_nombre || 'Sin mesero'}</Text>
          </View>
        </View>

        <View style={styles.tableFooter}>
          <Text style={styles.tableTotal}>{effectiveTotal > 0 ? money(effectiveTotal) : 'Sin consumo'}</Text>
          <Text style={styles.tableHint}>{table.status === 'libre' && !incomingOrder ? 'Reclamar' : 'Abrir'}</Text>
        </View>
      </TouchableOpacity>
    );
  }

  function renderTableFilter(filter: (typeof filters)[number]) {
    const active = activeFilter === filter.key;
    return (
      <TouchableOpacity
        key={filter.key}
        activeOpacity={0.86}
        style={[styles.filterChip, active && styles.filterChipActive]}
        onPress={() => setActiveFilter(filter.key)}
      >
        <Ionicons name={filter.icon} size={20} color={active ? '#FFFFFF' : '#475569'} />
        <Text numberOfLines={1} style={[styles.filterChipText, active && styles.filterChipTextActive]}>
          {filter.label}
        </Text>
        <Text style={[styles.filterChipCount, active && styles.filterChipCountActive]}>{Math.min(99, filter.count)}</Text>
      </TouchableOpacity>
    );
  }

  function renderZoneSection(group: { key: string; title: string; tables: WaiterTable[] }) {
    const activeTotal = group.tables.reduce((sum, table) => sum + Number(table.total || 0), 0);
    return renderTableSection(
      group.title,
      `${group.tables.length} mesas · ${money(activeTotal)} activo`,
      group.tables,
      'No hay mesas en esta área.',
      'map-outline'
    );
  }

  function renderTableSection(
    title: string,
    subtitle: string,
    sectionTables: WaiterTable[],
    emptyText: string,
    icon: StatusIcon
  ) {
    return (
      <View style={styles.tableSection}>
        <View style={styles.tableSectionHeader}>
          <View style={styles.sectionTitleRow}>
            <View style={styles.sectionIcon}>
              <Ionicons name={icon} size={18} color="#111827" />
            </View>
            <View style={styles.sectionTitleCopy}>
              <Text style={styles.tableSectionTitle}>{title}</Text>
              <Text style={styles.tableSectionSubtitle}>{subtitle}</Text>
            </View>
          </View>
          <View style={styles.countPill}>
            <Text style={styles.countText}>{Math.min(99, sectionTables.length)}</Text>
          </View>
        </View>
        {sectionTables.length > 0 ? (
          <View style={styles.tableGrid}>{sectionTables.map(renderTableCard)}</View>
        ) : (
          <Text style={styles.sectionEmptyText}>{emptyText}</Text>
        )}
      </View>
    );
  }

  async function handleClaimTable() {
    if (!selectedBranch || !claimingTable) return;
    if (!customerName.trim()) {
      Alert.alert('Nombre requerido', 'Ingresa el nombre del comensal para abrir la cuenta.');
      return;
    }

    try {
      setClaiming(true);
      const table = await claimWaiterTable({
        tableId: claimingTable.id,
        restaurantId: selectedBranch.id,
        clienteNombre: customerName.trim(),
      });
      setClaimingTable(null);
      setCustomerName('');
      await tablesQuery.refetch();
      router.push({
        pathname: '/(waiter)/table/[id]',
        params: {
          id: String(table.id),
          restaurantId: String(selectedBranch.id),
          tableLabel: table.label,
          clienteNombre: table.cliente_nombre ?? customerName.trim(),
        },
      });
    } catch (error) {
      Alert.alert('No se pudo reclamar', getApiError(error));
    } finally {
      setClaiming(false);
    }
  }

  async function handleLogout() {
    await unregisterSessionPush().catch(() => undefined);
    await logout();
    router.replace('/(auth)/login');
  }

  const loading = branchesQuery.isLoading || tablesQuery.isLoading;

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <View style={styles.headerCopy}>
          <Text style={styles.eyebrow}>Panel de mesero</Text>
          <Text style={styles.title} numberOfLines={1}>{user?.nombre ?? 'Mesero'}</Text>
          <View style={styles.branchInline}>
            <Ionicons name="location-outline" size={14} color="#64748B" />
            <Text style={styles.branchInlineText} numberOfLines={1}>{selectedBranch?.nombre ?? 'Sin sucursal asignada'}</Text>
          </View>
        </View>
        <View style={styles.headerActions}>
          <TouchableOpacity style={styles.headerIconButton} onPress={() => setGiftsVisible(true)} activeOpacity={0.8}>
            <Ionicons name="gift-outline" size={20} color="#111827" />
            {giftInbox.pending_count > 0 ? (
              <View style={styles.headerBadge}><Text style={styles.headerBadgeText}>{Math.min(99, giftInbox.pending_count)}</Text></View>
            ) : null}
          </TouchableOpacity>
          <TouchableOpacity style={styles.headerIconButton} onPress={() => tablesQuery.refetch()} activeOpacity={0.8}>
            {tablesQuery.isRefetching ? <ActivityIndicator size="small" color="#111827" /> : <Ionicons name="refresh" size={19} color="#111827" />}
          </TouchableOpacity>
          <TouchableOpacity style={styles.headerIconButton} onPress={handleLogout} activeOpacity={0.8}>
            <Ionicons name="log-out-outline" size={20} color="#111827" />
          </TouchableOpacity>
        </View>
      </View>

      {branches.length > 1 ? (
        <FlatList
          horizontal
          data={branches}
          keyExtractor={(branch) => String(branch.id)}
          contentContainerStyle={styles.branchList}
          showsHorizontalScrollIndicator={false}
          renderItem={({ item }) => (
            <TouchableOpacity
              activeOpacity={0.85}
              style={[styles.branchChip, selectedBranch?.id === item.id && styles.branchChipActive]}
              onPress={() => setSelectedBranchId(item.id)}
            >
              <Text style={[styles.branchChipText, selectedBranch?.id === item.id && styles.branchChipTextActive]} numberOfLines={1}>
                {item.nombre}
              </Text>
            </TouchableOpacity>
          )}
        />
      ) : null}

      {branches.length > 0 ? (
        <>
          <View style={styles.summaryPanel}>
            <View style={styles.summaryItem}>
              <Text style={styles.summaryValue}>{summary.mine}</Text>
              <Text style={styles.summaryLabel}>Mis mesas</Text>
            </View>
            <View style={styles.summaryDivider} />
            <View style={styles.summaryItem}>
              <Text style={styles.summaryValue}>{summary.free}</Text>
              <Text style={styles.summaryLabel}>Libres</Text>
            </View>
            <View style={styles.summaryDivider} />
            <View style={styles.summaryItem}>
              <Text style={styles.summaryValue}>{summary.support}</Text>
              <Text style={styles.summaryLabel}>Apoyo</Text>
            </View>
            <View style={styles.summaryDivider} />
            <View style={styles.summaryItemWide}>
              <Text style={styles.summaryValueSmall}>{money(summary.total)}</Text>
              <Text style={styles.summaryLabel}>Activo</Text>
            </View>
          </View>

          {giftInbox.active.length > 0 ? (
            <TouchableOpacity style={styles.giftBanner} activeOpacity={0.88} onPress={() => setGiftsVisible(true)}>
              <View style={styles.giftBannerIcon}><Ionicons name="gift" size={21} color="#FFFFFF" /></View>
              <View style={styles.giftBannerCopy}>
                <Text style={styles.giftBannerTitle}>{giftInbox.pending_count > 0 ? `${giftInbox.pending_count} regalos esperan entrega` : 'Entregas de regalos en curso'}</Text>
                <Text style={styles.giftBannerText}>Revisa destinos y coordina la entrega.</Text>
              </View>
              <Ionicons name="chevron-forward" size={20} color="#BE123C" />
            </TouchableOpacity>
          ) : null}

          {false && incomingOrders.length > 0 ? (
            <View style={styles.incomingPanel}>
              <View style={styles.incomingHeader}>
                <View>
                  <Text style={styles.incomingKicker}>Pedidos desde cliente</Text>
                  <Text style={styles.incomingTitle}>{incomingOrders.length} comandas activas</Text>
                </View>
                {incomingOrdersQuery.isRefetching ? <ActivityIndicator size="small" color="#92400E" /> : null}
              </View>
              <ScrollView
                style={undefined}
                nestedScrollEnabled
                showsVerticalScrollIndicator={false}
              >
                {null}
              </ScrollView>
              {incomingOrders.length > 4 ? (
                <TouchableOpacity
                  style={styles.incomingToggle}
                  activeOpacity={0.82}
                  onPress={() => undefined}
                >
                  <Text style={styles.incomingToggleText}>
                    {`Ver ${incomingOrders.length - 4} más`}
                  </Text>
                  <Ionicons
                    name="chevron-down"
                    size={17}
                    color="#C2410C"
                  />
                </TouchableOpacity>
              ) : null}
            </View>
          ) : null}

          <View style={styles.searchWrap}>
            <Ionicons name="search-outline" size={18} color="#64748B" />
            <TextInput
              value={searchText}
              onChangeText={setSearchText}
              placeholder="Buscar mesa, zona, comensal o mesero"
              placeholderTextColor="#94A3B8"
              style={styles.searchInput}
              autoCapitalize="none"
              autoCorrect={false}
              returnKeyType="search"
            />
            {searchText ? (
              <TouchableOpacity
                accessibilityLabel="Limpiar búsqueda"
                style={styles.clearSearchButton}
                onPress={() => setSearchText('')}
                activeOpacity={0.75}
              >
                <Ionicons name="close" size={16} color="#64748B" />
              </TouchableOpacity>
            ) : null}
          </View>

          <View style={styles.groupSwitch}>
            {([
              ['status', 'Estado', 'list-outline'],
              ['zone', 'Área', 'map-outline'],
            ] as Array<[typeof tableGrouping, string, StatusIcon]>).map(([value, label, icon]) => {
              const active = tableGrouping === value;
              return (
                <TouchableOpacity
                  key={value}
                  style={[styles.groupSwitchButton, active && styles.groupSwitchButtonActive]}
                  onPress={() => setTableGrouping(value)}
                  activeOpacity={0.85}
                >
                  <Ionicons name={icon} size={16} color={active ? '#FFFFFF' : '#475569'} />
                  <Text style={[styles.groupSwitchText, active && styles.groupSwitchTextActive]}>{label}</Text>
                </TouchableOpacity>
              );
            })}
          </View>
        </>
      ) : null}

      {branchesQuery.isSuccess && branches.length === 0 ? (
        <View style={styles.emptyState}>
          <Ionicons name="storefront-outline" size={44} color="#94A3B8" />
          <Text style={styles.emptyTitle}>Sin sucursales asignadas</Text>
          <Text style={styles.emptyText}>Pide al administrador que te asigne una sucursal como mesero.</Text>
        </View>
      ) : (
        <ScrollView
          contentContainerStyle={styles.tableList}
          showsVerticalScrollIndicator={false}
          refreshControl={
            <RefreshControl
              refreshing={tablesQuery.isRefetching}
              onRefresh={() => tablesQuery.refetch()}
              tintColor={Colors.primary}
            />
          }
        >
          {loading ? (
            <View style={styles.loadingState}>
              <ActivityIndicator color="#111827" />
              <Text style={styles.loadingText}>Cargando mesas...</Text>
            </View>
          ) : tables.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons name="grid-outline" size={44} color="#94A3B8" />
              <Text style={styles.emptyTitle}>No hay mesas configuradas</Text>
              <Text style={styles.emptyText}>Revisa la configuración de mesas de esta sucursal.</Text>
            </View>
          ) : filteredTables.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons name="search-outline" size={44} color="#94A3B8" />
              <Text style={styles.emptyTitle}>Sin resultados</Text>
              <Text style={styles.emptyText}>No encontramos mesas con ese nombre, zona o comensal.</Text>
            </View>
          ) : (
            tableGrouping === 'zone' ? (
              <>{zoneGroups.map((group) => <React.Fragment key={group.key}>{renderZoneSection(group)}</React.Fragment>)}</>
            ) : (
              <View style={styles.tableSection}>
                <View style={styles.filterRow}>{filters.map(renderTableFilter)}</View>
                {activeTables.length > 0 ? (
                  <View style={styles.tableGrid}>{activeTables.map(renderTableCard)}</View>
                ) : (
                  <Text style={styles.sectionEmptyText}>{activeEmptyText}</Text>
                )}
              </View>
            )
          )}
        </ScrollView>
      )}

      <Modal visible={Boolean(claimingTable)} transparent animationType="fade" onRequestClose={() => setClaimingTable(null)}>
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
          keyboardVerticalOffset={0}
          style={styles.modalKeyboard}
        >
          <Pressable
            style={styles.modalOverlay}
            onPress={() => {
              Keyboard.dismiss();
              setClaimingTable(null);
            }}
          >
            <Pressable
              style={[
                styles.modalCard,
                { paddingBottom: 22 + Math.max(insets.bottom, Platform.OS === 'android' ? 8 : 0) },
              ]}
              onPress={(event) => event.stopPropagation()}
            >
              <View style={styles.modalHandle} />
              <View style={styles.modalHeaderRow}>
                <View style={styles.modalIcon}>
                  <Ionicons name="restaurant-outline" size={20} color="#FFFFFF" />
                </View>
                <View style={styles.modalHeaderCopy}>
                  <Text style={styles.modalTitle}>Abrir {claimingTable?.label}</Text>
                  <Text style={styles.modalText}>Nombre del comensal responsable</Text>
                </View>
              </View>
              <TextInput
                value={customerName}
                onChangeText={setCustomerName}
                placeholder="Ej. Omar Bravo"
                placeholderTextColor="#94A3B8"
                style={styles.input}
                autoCapitalize="words"
                returnKeyType="done"
                blurOnSubmit
                onSubmitEditing={handleClaimTable}
              />
              <View style={styles.modalActions}>
                <TouchableOpacity style={styles.secondaryAction} onPress={() => setClaimingTable(null)} disabled={claiming}>
                  <Text style={styles.secondaryActionText}>Cancelar</Text>
                </TouchableOpacity>
                <TouchableOpacity style={styles.primaryAction} onPress={handleClaimTable} disabled={claiming}>
                  {claiming ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.primaryActionText}>Abrir cuenta</Text>}
                </TouchableOpacity>
              </View>
            </Pressable>
          </Pressable>
        </KeyboardAvoidingView>
      </Modal>

      {selectedBranch ? (
        <GiftInboxModal
          visible={giftsVisible}
          restaurantId={selectedBranch.id}
          inbox={giftInbox}
          onClose={() => setGiftsVisible(false)}
          onChanged={() => giftsQuery.refetch()}
        />
      ) : null}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: '#F4F6F8',
  },
  header: {
    paddingHorizontal: 20,
    paddingTop: 10,
    paddingBottom: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  headerCopy: {
    flex: 1,
    minWidth: 0,
  },
  eyebrow: {
    fontSize: 12,
    fontWeight: '900',
    color: '#64748B',
    textTransform: 'uppercase',
  },
  title: {
    marginTop: 2,
    fontSize: 32,
    fontWeight: '900',
    color: '#111827',
  },
  branchInline: {
    marginTop: 5,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  branchInlineText: {
    flex: 1,
    fontSize: 15,
    fontWeight: '800',
    color: '#64748B',
  },
  headerActions: {
    flexDirection: 'row',
    gap: 10,
  },
  headerIconButton: {
    width: 52,
    height: 52,
    borderRadius: 16,
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  headerBadge: { position: 'absolute', top: -4, right: -4, minWidth: 19, height: 19, borderRadius: 10, paddingHorizontal: 4, alignItems: 'center', justifyContent: 'center', backgroundColor: '#BE123C', borderWidth: 2, borderColor: '#F4F6F8' },
  headerBadgeText: { color: '#FFFFFF', fontSize: 9, fontWeight: '900' },
  branchList: {
    paddingHorizontal: 20,
    gap: 10,
    paddingBottom: 12,
  },
  branchChip: {
    maxWidth: 230,
    paddingHorizontal: 18,
    paddingVertical: 13,
    borderRadius: 16,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  giftBanner: { marginHorizontal: 20, marginBottom: 14, padding: 16, borderRadius: 18, borderWidth: 1, borderColor: '#FECDD3', flexDirection: 'row', alignItems: 'center', gap: 13, backgroundColor: '#FFF1F2' },
  giftBannerIcon: { width: 48, height: 48, borderRadius: 15, alignItems: 'center', justifyContent: 'center', backgroundColor: '#BE123C' },
  giftBannerCopy: { flex: 1 },
  giftBannerTitle: { color: '#881337', fontSize: 16, fontWeight: '900' },
  giftBannerText: { marginTop: 3, color: '#9F1239', fontSize: 13, fontWeight: '700' },
  incomingPanel: {
    marginHorizontal: 20,
    marginBottom: 14,
    padding: 16,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: '#FED7AA',
    backgroundColor: '#FFF7ED',
    gap: 12,
  },
  incomingHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 12,
  },
  incomingListExpanded: {
    maxHeight: 390,
  },
  incomingKicker: {
    fontSize: 10,
    fontWeight: '900',
    color: '#C2410C',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
  },
  incomingTitle: {
    marginTop: 2,
    fontSize: 18,
    fontWeight: '900',
    color: '#7C2D12',
  },
  incomingOrderCard: {
    minHeight: 76,
    borderRadius: 17,
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderWidth: 1,
    borderColor: '#FDBA74',
    backgroundColor: '#FFFFFF',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  incomingOrderIcon: {
    width: 46,
    height: 46,
    borderRadius: 14,
    backgroundColor: '#EA580C',
    alignItems: 'center',
    justifyContent: 'center',
  },
  incomingOrderCopy: {
    flex: 1,
    minWidth: 0,
  },
  incomingOrderTitle: {
    fontSize: 16,
    fontWeight: '900',
    color: '#111827',
  },
  incomingOrderText: {
    marginTop: 2,
    fontSize: 13,
    fontWeight: '700',
    color: '#9A3412',
  },
  incomingOrderStatus: {
    marginTop: 3,
    fontSize: 12,
    fontWeight: '900',
    color: '#B45309',
    textTransform: 'uppercase',
  },
  incomingOrderStatusReady: {
    color: '#15803D',
  },
  incomingOrderAction: {
    minWidth: 104,
    height: 46,
    borderRadius: 14,
    paddingHorizontal: 14,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#EA580C',
  },
  incomingOrderActionReady: {
    backgroundColor: '#16A34A',
  },
  incomingOrderActionDisabled: {
    opacity: 0.58,
  },
  incomingOrderActionText: {
    color: '#FFFFFF',
    fontSize: 13,
    fontWeight: '900',
  },
  incomingToggle: {
    height: 50,
    borderRadius: 15,
    borderWidth: 1,
    borderColor: '#FED7AA',
    backgroundColor: '#FFEDD5',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
  },
  incomingToggleText: {
    color: '#C2410C',
    fontSize: 14,
    fontWeight: '900',
  },
  tableGiftBadge: { marginLeft: 'auto', paddingHorizontal: 10, height: 32, borderRadius: 16, flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: '#FFE4E6' },
  tableGiftBadgeText: { color: '#BE123C', fontSize: 13, fontWeight: '900' },
  branchChipActive: {
    backgroundColor: '#111827',
    borderColor: '#111827',
  },
  branchChipText: {
    fontWeight: '900',
    color: '#475569',
  },
  branchChipTextActive: {
    color: '#FFFFFF',
  },
  summaryPanel: {
    marginHorizontal: 20,
    marginBottom: 14,
    minHeight: 102,
    borderRadius: 18,
    backgroundColor: '#111827',
    padding: 15,
    flexDirection: 'row',
    alignItems: 'center',
  },
  summaryItem: {
    flex: 1,
    alignItems: 'center',
  },
  summaryItemWide: {
    flex: 1.35,
    alignItems: 'center',
  },
  summaryDivider: {
    width: 1,
    height: 40,
    backgroundColor: 'rgba(255,255,255,0.14)',
  },
  summaryValue: {
    fontSize: 29,
    fontWeight: '900',
    color: '#FFFFFF',
  },
  summaryValueSmall: {
    fontSize: 20,
    fontWeight: '900',
    color: '#FFFFFF',
  },
  summaryLabel: {
    marginTop: 4,
    fontSize: 13,
    fontWeight: '800',
    color: '#CBD5E1',
  },
  searchWrap: {
    marginHorizontal: 20,
    marginBottom: 14,
    minHeight: 62,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#FFFFFF',
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    gap: 12,
  },
  searchInput: {
    flex: 1,
    minHeight: 60,
    color: '#111827',
    fontSize: 17,
    fontWeight: '800',
  },
  clearSearchButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: '#F1F5F9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  groupSwitch: {
    marginHorizontal: 20,
    marginBottom: 14,
    minHeight: 58,
    borderRadius: 18,
    padding: 5,
    flexDirection: 'row',
    gap: 4,
    backgroundColor: '#E2E8F0',
  },
  groupSwitchButton: {
    flex: 1,
    borderRadius: 15,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 7,
  },
  groupSwitchButtonActive: {
    backgroundColor: '#111827',
  },
  groupSwitchText: {
    color: '#475569',
    fontSize: 15,
    fontWeight: '900',
  },
  groupSwitchTextActive: {
    color: '#FFFFFF',
  },
  tableList: {
    paddingHorizontal: 16,
    paddingBottom: 118,
    gap: 16,
  },
  tableSection: {
    borderRadius: 20,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 12,
  },
  filterRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginBottom: 10,
  },
  filterChip: {
    flexGrow: 1,
    flexBasis: '100%',
    minHeight: 58,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    backgroundColor: '#F8FAFC',
    paddingHorizontal: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
  },
  filterChipActive: {
    backgroundColor: '#111827',
    borderColor: '#111827',
  },
  filterChipText: {
    flex: 1,
    color: '#475569',
    fontSize: 15,
    fontWeight: '900',
  },
  filterChipTextActive: {
    color: '#FFFFFF',
  },
  filterChipCount: {
    minWidth: 30,
    height: 30,
    borderRadius: 15,
    overflow: 'hidden',
    textAlign: 'center',
    textAlignVertical: 'center',
    backgroundColor: '#E2E8F0',
    color: '#334155',
    fontSize: 13,
    fontWeight: '900',
    paddingTop: 5,
  },
  filterChipCountActive: {
    backgroundColor: 'rgba(255,255,255,0.18)',
    color: '#FFFFFF',
  },
  tableSectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
    gap: 10,
  },
  sectionTitleRow: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  sectionIcon: {
    width: 46,
    height: 46,
    borderRadius: 14,
    backgroundColor: '#F1F5F9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  sectionTitleCopy: {
    flex: 1,
    minWidth: 0,
  },
  tableSectionTitle: {
    fontSize: 19,
    fontWeight: '900',
    color: '#111827',
  },
  tableSectionSubtitle: {
    marginTop: 2,
    fontSize: 14,
    fontWeight: '700',
    color: '#64748B',
  },
  countPill: {
    minWidth: 42,
    height: 42,
    borderRadius: 14,
    backgroundColor: '#111827',
    alignItems: 'center',
    justifyContent: 'center',
  },
  countText: {
    fontSize: 15,
    fontWeight: '900',
    color: '#FFFFFF',
  },
  tableGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  tableCard: {
    width: '100%',
    minHeight: 190,
    borderRadius: 18,
    backgroundColor: '#F8FAFC',
    padding: 14,
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },
  tableTopRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
  },
  statusBadge: {
    flex: 1,
    minHeight: 38,
    borderRadius: 999,
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  statusBadgeText: {
    flex: 1,
    fontSize: 13,
    fontWeight: '900',
  },
  tableIdentity: {
    marginTop: 14,
  },
  tableLabel: {
    fontSize: 31,
    fontWeight: '900',
    color: '#111827',
  },
  zoneName: {
    marginTop: 2,
    fontSize: 15,
    fontWeight: '800',
    color: '#64748B',
  },
  tableMeta: {
    marginTop: 12,
    gap: 8,
  },
  metaLine: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  metaText: {
    flex: 1,
    fontSize: 15,
    color: '#475569',
    fontWeight: '800',
  },
  tableFooter: {
    marginTop: 'auto',
    paddingTop: 12,
    flexDirection: 'row',
    alignItems: 'flex-end',
    justifyContent: 'space-between',
    gap: 8,
  },
  tableTotal: {
    flex: 1,
    fontSize: 20,
    fontWeight: '900',
    color: '#111827',
  },
  tableHint: {
    fontSize: 16,
    fontWeight: '900',
    color: '#2563EB',
  },
  sectionEmptyText: {
    paddingVertical: 12,
    paddingHorizontal: 4,
    color: '#94A3B8',
    fontWeight: '800',
  },
  loadingState: {
    minHeight: 180,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  loadingText: {
    fontWeight: '800',
    color: '#64748B',
  },
  emptyState: {
    margin: 18,
    borderRadius: 20,
    padding: 24,
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  emptyTitle: {
    marginTop: 12,
    fontSize: 18,
    fontWeight: '900',
    color: '#111827',
  },
  emptyText: {
    marginTop: 6,
    textAlign: 'center',
    lineHeight: 20,
    color: '#64748B',
    fontWeight: '700',
  },
  modalKeyboard: {
    flex: 1,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.48)',
    justifyContent: 'flex-end',
  },
  modalCard: {
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    backgroundColor: '#FFFFFF',
    paddingHorizontal: 18,
    paddingTop: 10,
    paddingBottom: 22,
  },
  modalHandle: {
    alignSelf: 'center',
    width: 42,
    height: 5,
    borderRadius: 999,
    backgroundColor: '#CBD5E1',
    marginBottom: 16,
  },
  modalHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  modalIcon: {
    width: 44,
    height: 44,
    borderRadius: 14,
    backgroundColor: '#111827',
    alignItems: 'center',
    justifyContent: 'center',
  },
  modalHeaderCopy: {
    flex: 1,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '900',
    color: '#111827',
  },
  modalText: {
    marginTop: 3,
    fontSize: 13,
    color: '#64748B',
    fontWeight: '700',
  },
  input: {
    marginTop: 18,
    minHeight: 52,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#CBD5E1',
    paddingHorizontal: 14,
    color: '#111827',
    fontWeight: '900',
    backgroundColor: '#F8FAFC',
  },
  modalActions: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 16,
  },
  secondaryAction: {
    flex: 1,
    minHeight: 52,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#CBD5E1',
    alignItems: 'center',
    justifyContent: 'center',
  },
  secondaryActionText: {
    fontWeight: '900',
    color: '#111827',
  },
  primaryAction: {
    flex: 1,
    minHeight: 52,
    borderRadius: 16,
    backgroundColor: '#111827',
    alignItems: 'center',
    justifyContent: 'center',
  },
  primaryActionText: {
    fontWeight: '900',
    color: '#FFFFFF',
  },
});
