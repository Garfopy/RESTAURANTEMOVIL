import React, { useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
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
import type { Sucursal } from '@amare/types';
import {
  getHostessBranches,
  getHostessTables,
  type HostessTable,
  type HostessTableStatus,
} from '../../services/hostess.service';
import { useUserStore } from '../../store/user.store';

const STATUS_META: Record<HostessTableStatus, { label: string; color: string; bg: string; icon: keyof typeof Ionicons.glyphMap }> = {
  libre: { label: 'Libre', color: '#047857', bg: '#ECFDF5', icon: 'checkmark-circle-outline' },
  ocupada: { label: 'Ocupada', color: '#B45309', bg: '#FFFBEB', icon: 'people-outline' },
  reservada: { label: 'Reservada', color: '#6D28D9', bg: '#F5F3FF', icon: 'calendar-outline' },
  pagando: { label: 'Pagando', color: '#0369A1', bg: '#EFF6FF', icon: 'card-outline' },
};

type Filter = 'todas' | HostessTableStatus;

export default function HostessTablesScreen() {
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);
  const [filter, setFilter] = useState<Filter>('todas');
  const user = useUserStore((state) => state.user);

  const branchesQuery = useQuery({
    queryKey: ['hostess', 'branches'],
    queryFn: getHostessBranches,
  });
  const branches = branchesQuery.data ?? [];
  const selectedBranch = useMemo(
    () => branches.find((branch) => Number(branch.id) === Number(selectedBranchId)) ?? branches[0] ?? null,
    [branches, selectedBranchId]
  );

  useEffect(() => {
    if (!selectedBranchId && branches.length > 0) {
      setSelectedBranchId(Number(branches[0].id));
    }
  }, [branches, selectedBranchId]);

  const tablesQuery = useQuery({
    queryKey: ['hostess', 'tables', selectedBranch?.id],
    queryFn: () => getHostessTables(Number(selectedBranch!.id)),
    enabled: Boolean(selectedBranch?.id),
    refetchInterval: 12_000,
    refetchIntervalInBackground: false,
  });

  const tables = tablesQuery.data?.tables ?? [];
  const summary = tablesQuery.data?.summary;
  const availabilityPct = summary?.total ? Math.round(((summary.libres || 0) / summary.total) * 100) : 0;
  const visibleTables = useMemo(
    () => filter === 'todas' ? tables : tables.filter((table) => table.status === filter),
    [filter, tables]
  );
  const zones = useMemo(() => {
    const groups = new Map<string, { title: string; tables: HostessTable[] }>();
    visibleTables.forEach((table) => {
      const key = table.zona_nombre || 'Salon';
      const group = groups.get(key) ?? { title: key, tables: [] };
      group.tables.push(table);
      groups.set(key, group);
    });
    return Array.from(groups.values());
  }, [visibleTables]);

  const operatorName = String(user?.nombre ?? '').trim().split(/\s+/)[0] || 'Hostess';
  const refreshing = branchesQuery.isRefetching || tablesQuery.isRefetching;

  async function refresh() {
    await Promise.all([branchesQuery.refetch(), tablesQuery.refetch()]);
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <View>
          <Text style={styles.kicker}>Hostess</Text>
          <Text style={styles.title}>Mesas</Text>
          <Text style={styles.subtitle}>Hola, {operatorName}</Text>
        </View>
        <TouchableOpacity style={styles.refreshButton} onPress={() => tablesQuery.refetch()} activeOpacity={0.8}>
          {tablesQuery.isRefetching ? <ActivityIndicator size="small" color="#111827" /> : <Ionicons name="refresh" size={20} color="#111827" />}
        </TouchableOpacity>
      </View>

      {branches.length > 1 ? (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.branchRow}>
          {branches.map((branch: Sucursal) => {
            const active = Number(branch.id) === Number(selectedBranch?.id);
            return (
              <TouchableOpacity
                key={branch.id}
                style={[styles.branchChip, active && styles.branchChipActive]}
                onPress={() => setSelectedBranchId(Number(branch.id))}
                activeOpacity={0.85}
              >
                <Text style={[styles.branchText, active && styles.branchTextActive]} numberOfLines={1}>{branch.nombre}</Text>
              </TouchableOpacity>
            );
          })}
        </ScrollView>
      ) : null}

      <View style={styles.availabilityPanel}>
        <View style={styles.availabilityTop}>
          <View>
            <Text style={styles.panelKicker}>Disponibilidad</Text>
            <Text style={styles.panelTitle}>{summary?.wait_label ?? 'Sin espera'}</Text>
          </View>
          <View style={styles.percentBadge}>
            <Text style={styles.percentText}>{availabilityPct}%</Text>
          </View>
        </View>
        <View style={styles.progressTrack}>
          <View style={[styles.progressFill, { width: `${availabilityPct}%` }]} />
        </View>
        <View style={styles.summaryGrid}>
          <SummaryCard label="Libres" value={summary?.libres ?? 0} icon="checkmark-circle-outline" color="#047857" />
          <SummaryCard label="Ocupadas" value={summary?.ocupadas ?? 0} icon="people-outline" color="#B45309" />
          <SummaryCard label="Reservas" value={summary?.reservadas ?? 0} icon="calendar-outline" color="#6D28D9" />
        </View>
      </View>

      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        style={styles.filterScroller}
        contentContainerStyle={styles.filterRow}
      >
        <FilterChip label="Todas" active={filter === 'todas'} onPress={() => setFilter('todas')} count={tables.length} />
        <FilterChip label="Libres" active={filter === 'libre'} onPress={() => setFilter('libre')} count={summary?.libres ?? 0} />
        <FilterChip label="Ocupadas" active={filter === 'ocupada'} onPress={() => setFilter('ocupada')} count={summary?.ocupadas ?? 0} />
        <FilterChip label="Reservadas" active={filter === 'reservada'} onPress={() => setFilter('reservada')} count={summary?.reservadas ?? 0} />
      </ScrollView>

      {tablesQuery.isLoading ? (
        <View style={styles.centerState}>
          <ActivityIndicator color="#111827" />
          <Text style={styles.stateText}>Cargando mesas</Text>
        </View>
      ) : (
        <FlatList
          data={zones}
          keyExtractor={(item) => item.title}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} />}
          contentContainerStyle={styles.listContent}
          ListEmptyComponent={<Text style={styles.emptyText}>No hay mesas para mostrar.</Text>}
          renderItem={({ item }) => (
            <View style={styles.zoneSection}>
              <Text style={styles.zoneTitle}>{item.title}</Text>
              <View style={styles.tableGrid}>
                {item.tables.map((table) => <TableCard key={table.id} table={table} />)}
              </View>
            </View>
          )}
        />
      )}
    </SafeAreaView>
  );
}

function SummaryCard({ label, value, icon, color, wide }: { label: string; value: string | number; icon: keyof typeof Ionicons.glyphMap; color: string; wide?: boolean }) {
  return (
    <View style={[styles.summaryCard, wide && styles.summaryCardWide]}>
      <Ionicons name={icon} size={20} color={color} />
      <Text style={styles.summaryValue} numberOfLines={1}>{value}</Text>
      <Text style={styles.summaryLabel}>{label}</Text>
    </View>
  );
}

function FilterChip({ label, count, active, onPress }: { label: string; count: number; active: boolean; onPress: () => void }) {
  return (
    <TouchableOpacity style={[styles.filterChip, active && styles.filterChipActive]} onPress={onPress} activeOpacity={0.85}>
      <Text style={[styles.filterText, active && styles.filterTextActive]}>{label}</Text>
      <Text style={[styles.filterCount, active && styles.filterCountActive]}>{Math.min(99, count)}</Text>
    </TouchableOpacity>
  );
}

function TableCard({ table }: { table: HostessTable }) {
  const meta = STATUS_META[table.status] ?? STATUS_META.libre;
  return (
    <View style={[styles.tableCard, table.status === 'libre' && styles.tableCardFree]}>
      <View style={[styles.tableAccent, { backgroundColor: meta.color }]} />
      <View style={styles.tableTop}>
        <Text style={styles.tableLabel} numberOfLines={1}>{table.label}</Text>
        <View style={[styles.statusPill, { backgroundColor: meta.bg }]}>
          <Ionicons name={meta.icon} size={14} color={meta.color} />
          <Text style={[styles.statusText, { color: meta.color }]}>{meta.label}</Text>
        </View>
      </View>
      <Text style={styles.tableMeta} numberOfLines={1}>{table.zona_nombre || 'Salon'}</Text>
      {table.ocupada ? (
        <Text style={styles.tableDetail} numberOfLines={1}>
          {table.cliente_nombre || table.mesero_nombre || 'Cuenta activa'}
        </Text>
      ) : (
        <Text style={styles.freeDetail}>Lista para asignar</Text>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#F4F6F8' },
  header: {
    paddingHorizontal: 20,
    paddingTop: 12,
    paddingBottom: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  kicker: { color: '#64748B', fontSize: 12, fontWeight: '900', textTransform: 'uppercase' },
  title: { color: '#111827', fontSize: 32, fontWeight: '900' },
  subtitle: { color: '#6B7280', fontSize: 15, fontWeight: '700', marginTop: 2 },
  refreshButton: {
    width: 44,
    height: 44,
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#E1E7EF',
  },
  branchRow: { paddingHorizontal: 20, gap: 10, paddingBottom: 12 },
  branchChip: {
    paddingHorizontal: 16,
    height: 42,
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#E1E7EF',
  },
  branchChipActive: { backgroundColor: '#111827', borderColor: '#111827' },
  branchText: { color: '#111827', fontWeight: '800' },
  branchTextActive: { color: '#FFFFFF' },
  availabilityPanel: {
    marginHorizontal: 20,
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    padding: 16,
    marginBottom: 14,
    borderWidth: 1,
    borderColor: '#E1E7EF',
    shadowColor: '#111827',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.05,
    shadowRadius: 14,
    elevation: 2,
  },
  availabilityTop: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    marginBottom: 14,
  },
  panelKicker: { color: '#64748B', fontSize: 12, fontWeight: '900', textTransform: 'uppercase' },
  panelTitle: { color: '#111827', fontSize: 26, fontWeight: '900', marginTop: 2 },
  percentBadge: {
    minWidth: 60,
    height: 40,
    borderRadius: 8,
    backgroundColor: '#F4F7FB',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 10,
  },
  percentText: { color: '#111827', fontWeight: '900' },
  progressTrack: {
    height: 8,
    borderRadius: 4,
    backgroundColor: '#E1E7EF',
    overflow: 'hidden',
    marginBottom: 14,
  },
  progressFill: {
    height: '100%',
    minWidth: 4,
    borderRadius: 4,
    backgroundColor: '#047857',
  },
  summaryGrid: {
    flexDirection: 'row',
    gap: 8,
  },
  summaryCard: {
    flex: 1,
    minHeight: 82,
    borderRadius: 8,
    backgroundColor: '#F7FAFC',
    padding: 12,
    justifyContent: 'space-between',
  },
  summaryCardWide: { flex: 1.35 },
  summaryValue: { color: '#111827', fontSize: 22, fontWeight: '900' },
  summaryLabel: { color: '#6B7280', fontSize: 12, fontWeight: '800' },
  filterScroller: {
    flexGrow: 0,
    minHeight: 50,
    maxHeight: 50,
    marginBottom: 14,
  },
  filterRow: {
    paddingHorizontal: 20,
    gap: 8,
    alignItems: 'center',
    paddingBottom: 2,
  },
  filterChip: {
    height: 42,
    minWidth: 96,
    paddingHorizontal: 12,
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E1E7EF',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  filterChipActive: { backgroundColor: '#111827', borderColor: '#111827' },
  filterText: { color: '#475569', fontSize: 15, fontWeight: '900' },
  filterTextActive: { color: '#FFFFFF' },
  filterCount: { color: '#64748B', fontSize: 15, fontWeight: '900' },
  filterCountActive: { color: '#FFFFFF' },
  centerState: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 10 },
  stateText: { color: '#6B7280', fontWeight: '800' },
  listContent: { paddingHorizontal: 20, paddingTop: 2, paddingBottom: 104 },
  zoneSection: { marginBottom: 20 },
  zoneTitle: { color: '#111827', fontSize: 18, fontWeight: '900', marginBottom: 10 },
  tableGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  tableCard: {
    width: '48%',
    minHeight: 136,
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    padding: 14,
    justifyContent: 'space-between',
    borderWidth: 1,
    borderColor: '#E1E7EF',
    overflow: 'hidden',
    shadowColor: '#111827',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.04,
    shadowRadius: 8,
    elevation: 1,
  },
  tableCardFree: { borderColor: '#BBF7D0' },
  tableAccent: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    height: 4,
  },
  tableTop: { gap: 10 },
  tableLabel: { color: '#111827', fontSize: 20, fontWeight: '900' },
  statusPill: {
    alignSelf: 'flex-start',
    borderRadius: 16,
    paddingHorizontal: 9,
    paddingVertical: 5,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  statusText: { fontSize: 11, fontWeight: '900' },
  tableMeta: { color: '#64748B', fontWeight: '800', marginTop: 12 },
  tableDetail: { color: '#111827', fontWeight: '800' },
  freeDetail: { color: '#047857', fontWeight: '900' },
  emptyText: { color: '#6B7280', fontWeight: '800', textAlign: 'center', marginTop: 40 },
});
