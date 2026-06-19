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
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { getApiError } from '../../services/api';
import {
  claimWaiterTable,
  getWaiterBranches,
  getWaiterTables,
  type WaiterTable,
} from '../../services/waiter.service';
import { useUserStore } from '../../store/user.store';
import { Colors } from '../../theme';

type StatusIcon = keyof typeof Ionicons.glyphMap;

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
  const user = useUserStore((state) => state.user);
  const logout = useUserStore((state) => state.logout);
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);
  const [searchText, setSearchText] = useState('');
  const [claimingTable, setClaimingTable] = useState<WaiterTable | null>(null);
  const [customerName, setCustomerName] = useState('');
  const [claiming, setClaiming] = useState(false);

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
  });

  const tables = tablesQuery.data ?? [];
  const filteredTables = useMemo(() => {
    const query = normalizeSearchText(searchText);
    if (!query) return tables;

    return tables.filter((table) =>
      [
        table.label,
        table.value,
        table.zona_nombre,
        table.cliente_nombre,
        table.mesero_nombre,
        table.estado,
        table.id,
      ].some((value) => normalizeSearchText(value).includes(query))
    );
  }, [tables, searchText]);

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
  const freeTables = filteredTables.filter((table) => table.status === 'libre');

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
    const free = tables.filter((table) => table.status === 'libre').length;
    const total = tables.reduce((sum, table) => sum + Number(table.total || 0), 0);
    return { mine, support, free, total };
  }, [tables, user?.id]);

  function openTable(table: WaiterTable) {
    if (!selectedBranch) return;

    if (table.status === 'libre') {
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
        clienteNombre: table.cliente_nombre ?? '',
        meseroNombre: table.mesero_nombre ?? '',
        supportMode: table.mesero_usuario_id && table.mesero_usuario_id !== user?.id ? '1' : '0',
      },
    });
  }

  function renderTableCard(table: WaiterTable) {
    return (
      <TouchableOpacity key={table.id} activeOpacity={0.9} style={styles.tableCard} onPress={() => openTable(table)}>
        <View style={styles.tableTopRow}>
          <View style={[styles.statusBadge, { backgroundColor: STATUS_BG[table.status] }]}>
            <Ionicons name={STATUS_ICON[table.status]} size={15} color={STATUS_COLOR[table.status]} />
            <Text style={[styles.statusBadgeText, { color: STATUS_COLOR[table.status] }]}>
              {STATUS_LABEL[table.status]}
            </Text>
          </View>
          <Ionicons name="chevron-forward" size={18} color="#94A3B8" />
        </View>

        <View style={styles.tableIdentity}>
          <Text style={styles.tableLabel} numberOfLines={1}>{table.label}</Text>
          {table.zona_nombre ? <Text style={styles.zoneName} numberOfLines={1}>{table.zona_nombre}</Text> : null}
        </View>

        <View style={styles.tableMeta}>
          <View style={styles.metaLine}>
            <Ionicons name="person-outline" size={14} color="#64748B" />
            <Text style={styles.metaText} numberOfLines={1}>{table.cliente_nombre || 'Sin comensal'}</Text>
          </View>
          <View style={styles.metaLine}>
            <Ionicons name="restaurant-outline" size={14} color="#64748B" />
            <Text style={styles.metaText} numberOfLines={1}>{table.mesero_nombre || 'Sin mesero'}</Text>
          </View>
        </View>

        <View style={styles.tableFooter}>
          <Text style={styles.tableTotal}>{Number(table.total || 0) > 0 ? money(table.total) : 'Sin consumo'}</Text>
          <Text style={styles.tableHint}>{table.status === 'libre' ? 'Reclamar' : 'Abrir'}</Text>
        </View>
      </TouchableOpacity>
    );
  }

  function renderTableSection(title: string, subtitle: string, data: WaiterTable[], empty: string, icon: StatusIcon) {
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
            <Text style={styles.countText}>{data.length}</Text>
          </View>
        </View>
        {data.length > 0 ? (
          <View style={styles.tableGrid}>{data.map(renderTableCard)}</View>
        ) : (
          <Text style={styles.sectionEmptyText}>{empty}</Text>
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
                accessibilityLabel="Limpiar busqueda"
                style={styles.clearSearchButton}
                onPress={() => setSearchText('')}
                activeOpacity={0.75}
              >
                <Ionicons name="close" size={16} color="#64748B" />
              </TouchableOpacity>
            ) : null}
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
              <Text style={styles.emptyText}>Revisa la configuracion de mesas de esta sucursal.</Text>
            </View>
          ) : filteredTables.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons name="search-outline" size={44} color="#94A3B8" />
              <Text style={styles.emptyTitle}>Sin resultados</Text>
              <Text style={styles.emptyText}>No encontramos mesas con ese nombre, zona o comensal.</Text>
            </View>
          ) : (
            <>
              {renderTableSection('Mis mesas', 'Cuentas asignadas a ti.', myTables, 'Todavia no tienes mesas asignadas.', 'person-outline')}
              {renderTableSection('Disponibles', 'Toca una mesa libre para reclamarla.', freeTables, 'No hay mesas libres en este momento.', 'grid-outline')}
              {renderTableSection('Apoyo', 'Mesas ocupadas que puedes apoyar.', supportTables, 'No hay mesas ocupadas por otros meseros.', 'people-outline')}
            </>
          )}
        </ScrollView>
      )}

      <Modal visible={Boolean(claimingTable)} transparent animationType="fade" onRequestClose={() => setClaimingTable(null)}>
        <Pressable style={styles.modalOverlay} onPress={() => setClaimingTable(null)}>
          <Pressable style={styles.modalCard} onPress={(event) => event.stopPropagation()}>
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
              placeholder="Ej. Armando Casas"
              placeholderTextColor="#94A3B8"
              style={styles.input}
              autoCapitalize="words"
              returnKeyType="done"
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
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: '#F4F6F8',
  },
  header: {
    paddingHorizontal: 18,
    paddingTop: 8,
    paddingBottom: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  headerCopy: {
    flex: 1,
    minWidth: 0,
  },
  eyebrow: {
    fontSize: 11,
    fontWeight: '900',
    color: '#64748B',
    textTransform: 'uppercase',
  },
  title: {
    marginTop: 2,
    fontSize: 27,
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
    fontSize: 13,
    fontWeight: '800',
    color: '#64748B',
  },
  headerActions: {
    flexDirection: 'row',
    gap: 8,
  },
  headerIconButton: {
    width: 42,
    height: 42,
    borderRadius: 14,
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  branchList: {
    paddingHorizontal: 18,
    gap: 8,
    paddingBottom: 10,
  },
  branchChip: {
    maxWidth: 190,
    paddingHorizontal: 14,
    paddingVertical: 9,
    borderRadius: 14,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
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
    marginHorizontal: 18,
    marginBottom: 12,
    minHeight: 82,
    borderRadius: 18,
    backgroundColor: '#111827',
    padding: 12,
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
    fontSize: 23,
    fontWeight: '900',
    color: '#FFFFFF',
  },
  summaryValueSmall: {
    fontSize: 16,
    fontWeight: '900',
    color: '#FFFFFF',
  },
  summaryLabel: {
    marginTop: 4,
    fontSize: 11,
    fontWeight: '800',
    color: '#CBD5E1',
  },
  searchWrap: {
    marginHorizontal: 18,
    marginBottom: 12,
    minHeight: 50,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#FFFFFF',
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    gap: 10,
  },
  searchInput: {
    flex: 1,
    minHeight: 48,
    color: '#111827',
    fontSize: 14,
    fontWeight: '800',
  },
  clearSearchButton: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: '#F1F5F9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  tableList: {
    paddingHorizontal: 14,
    paddingBottom: 28,
    gap: 14,
  },
  tableSection: {
    borderRadius: 20,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 12,
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
    width: 36,
    height: 36,
    borderRadius: 12,
    backgroundColor: '#F1F5F9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  sectionTitleCopy: {
    flex: 1,
    minWidth: 0,
  },
  tableSectionTitle: {
    fontSize: 16,
    fontWeight: '900',
    color: '#111827',
  },
  tableSectionSubtitle: {
    marginTop: 2,
    fontSize: 12,
    fontWeight: '700',
    color: '#64748B',
  },
  countPill: {
    minWidth: 34,
    height: 34,
    borderRadius: 12,
    backgroundColor: '#111827',
    alignItems: 'center',
    justifyContent: 'center',
  },
  countText: {
    fontSize: 13,
    fontWeight: '900',
    color: '#FFFFFF',
  },
  tableGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  tableCard: {
    width: '48.5%',
    minHeight: 174,
    borderRadius: 18,
    backgroundColor: '#F8FAFC',
    padding: 12,
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
    minHeight: 30,
    borderRadius: 999,
    paddingHorizontal: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  statusBadgeText: {
    flex: 1,
    fontSize: 11,
    fontWeight: '900',
  },
  tableIdentity: {
    marginTop: 14,
  },
  tableLabel: {
    fontSize: 23,
    fontWeight: '900',
    color: '#111827',
  },
  zoneName: {
    marginTop: 2,
    fontSize: 12,
    fontWeight: '800',
    color: '#64748B',
  },
  tableMeta: {
    marginTop: 12,
    gap: 7,
  },
  metaLine: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  metaText: {
    flex: 1,
    fontSize: 12,
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
    fontSize: 15,
    fontWeight: '900',
    color: '#111827',
  },
  tableHint: {
    fontSize: 12,
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
