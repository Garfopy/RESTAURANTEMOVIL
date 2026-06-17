import React, { useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Modal,
  Pressable,
  RefreshControl,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import type { Sucursal } from '@amare/types';
import { getApiError } from '../../services/api';
import {
  claimWaiterTable,
  getWaiterBranches,
  getWaiterTables,
  type WaiterTable,
} from '../../services/waiter.service';
import { useUserStore } from '../../store/user.store';
import { Colors } from '../../theme';

const STATUS_LABEL: Record<WaiterTable['status'], string> = {
  libre: 'Libre',
  mia: 'Mi mesa',
  cuenta_abierta: 'Cuenta abierta',
  ocupada_por_otro: 'Ocupada',
};

const STATUS_COLOR: Record<WaiterTable['status'], string> = {
  libre: '#16A34A',
  mia: '#2563EB',
  cuenta_abierta: '#B45309',
  ocupada_por_otro: '#6B7280',
};

function normalizeSearchText(value?: string | number | null): string {
  return String(value ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim();
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
    if (!query) {
      return tables;
    }

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
      <TouchableOpacity key={table.id} activeOpacity={0.88} style={styles.tableCard} onPress={() => openTable(table)}>
        <View style={[styles.statusDot, { backgroundColor: STATUS_COLOR[table.status] }]} />
        <Text style={styles.tableLabel}>{table.label}</Text>
        {table.zona_nombre ? <Text style={styles.zoneName}>{table.zona_nombre}</Text> : null}
        <Text style={[styles.tableStatus, { color: STATUS_COLOR[table.status] }]}>
          {STATUS_LABEL[table.status]}
        </Text>
        {table.cliente_nombre ? <Text style={styles.customerName}>{table.cliente_nombre}</Text> : null}
        {table.mesero_nombre ? <Text style={styles.waiterName}>{table.mesero_nombre}</Text> : null}
        {table.total > 0 ? <Text style={styles.tableTotal}>${table.total.toFixed(2)}</Text> : null}
      </TouchableOpacity>
    );
  }

  function renderTableSection(title: string, subtitle: string, data: WaiterTable[], empty: string) {
    return (
      <View style={styles.tableSection}>
        <View style={styles.tableSectionHeader}>
          <View>
            <Text style={styles.tableSectionTitle}>{title}</Text>
            <Text style={styles.tableSectionSubtitle}>{subtitle}</Text>
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
        <View>
          <Text style={styles.eyebrow}>Panel de mesero</Text>
          <Text style={styles.title}>{user?.nombre ?? 'Mesero'}</Text>
        </View>
        <TouchableOpacity style={styles.logoutButton} onPress={handleLogout} activeOpacity={0.8}>
          <Ionicons name="log-out-outline" size={20} color="#111827" />
        </TouchableOpacity>
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
              <Text style={[styles.branchChipText, selectedBranch?.id === item.id && styles.branchChipTextActive]}>
                {item.nombre}
              </Text>
            </TouchableOpacity>
          )}
        />
      ) : null}

      <View style={styles.sectionHeader}>
        <View>
          <Text style={styles.sectionTitle}>{selectedBranch?.nombre ?? 'Sin sucursal asignada'}</Text>
          <Text style={styles.sectionSubtitle}>Administra tus mesas o apoya una cuenta ocupada.</Text>
        </View>
        {loading ? <ActivityIndicator color={Colors.primary} /> : null}
      </View>

      {branches.length > 0 ? (
        <View style={styles.searchWrap}>
          <Ionicons name="search-outline" size={18} color="#6B7280" />
          <TextInput
            value={searchText}
            onChangeText={setSearchText}
            placeholder="Buscar mesa o zona"
            placeholderTextColor="#9CA3AF"
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
              <Ionicons name="close" size={16} color="#6B7280" />
            </TouchableOpacity>
          ) : null}
        </View>
      ) : null}

      {branchesQuery.isSuccess && branches.length === 0 ? (
        <View style={styles.emptyState}>
          <Ionicons name="storefront-outline" size={44} color="#9CA3AF" />
          <Text style={styles.emptyTitle}>Sin sucursales asignadas</Text>
          <Text style={styles.emptyText}>Pide al administrador que te asigne una sucursal como mesero.</Text>
        </View>
      ) : (
        <ScrollView
          contentContainerStyle={styles.tableList}
          refreshControl={
            <RefreshControl
              refreshing={tablesQuery.isRefetching}
              onRefresh={() => tablesQuery.refetch()}
              tintColor={Colors.primary}
            />
          }
        >
          {!loading && tables.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons name="grid-outline" size={44} color="#9CA3AF" />
              <Text style={styles.emptyTitle}>No hay mesas configuradas</Text>
              <Text style={styles.emptyText}>Revisa la configuracion de mesas de esta sucursal.</Text>
            </View>
          ) : !loading && filteredTables.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons name="search-outline" size={44} color="#9CA3AF" />
              <Text style={styles.emptyTitle}>Sin resultados</Text>
              <Text style={styles.emptyText}>No encontramos mesas con ese nombre o zona.</Text>
            </View>
          ) : (
            <>
              {renderTableSection('Mis mesas', 'Cuentas reclamadas por ti.', myTables, 'Todavia no tienes mesas asignadas.')}
              {renderTableSection('Ocupadas / apoyo', 'Puedes agregar productos si te lo piden.', supportTables, 'No hay mesas ocupadas por otros meseros.')}
              {renderTableSection('Disponibles', 'Toca una mesa libre para reclamarla.', freeTables, 'No hay mesas libres en este momento.')}
            </>
          )}
        </ScrollView>
      )}

      <Modal visible={Boolean(claimingTable)} transparent animationType="fade" onRequestClose={() => setClaimingTable(null)}>
        <Pressable style={styles.modalOverlay} onPress={() => setClaimingTable(null)}>
          <Pressable style={styles.modalCard} onPress={(event) => event.stopPropagation()}>
            <Text style={styles.modalTitle}>Reclamar {claimingTable?.label}</Text>
            <Text style={styles.modalText}>Escribe el nombre del comensal responsable de la cuenta.</Text>
            <TextInput
              value={customerName}
              onChangeText={setCustomerName}
              placeholder="Nombre del comensal"
              placeholderTextColor="#9CA3AF"
              style={styles.input}
              autoCapitalize="words"
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
    backgroundColor: '#F8FAFC',
  },
  header: {
    paddingHorizontal: 20,
    paddingTop: 8,
    paddingBottom: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  eyebrow: {
    fontSize: 12,
    fontWeight: '800',
    color: '#6B7280',
    textTransform: 'uppercase',
  },
  title: {
    marginTop: 2,
    fontSize: 26,
    fontWeight: '900',
    color: '#111827',
  },
  logoutButton: {
    width: 42,
    height: 42,
    borderRadius: 21,
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
  },
  branchList: {
    paddingHorizontal: 20,
    gap: 10,
    paddingBottom: 10,
  },
  branchChip: {
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 999,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  branchChipActive: {
    backgroundColor: '#111827',
    borderColor: '#111827',
  },
  branchChipText: {
    fontWeight: '800',
    color: '#4B5563',
  },
  branchChipTextActive: {
    color: '#FFFFFF',
  },
  sectionHeader: {
    paddingHorizontal: 20,
    paddingVertical: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '900',
    color: '#111827',
  },
  sectionSubtitle: {
    marginTop: 3,
    fontSize: 13,
    color: '#6B7280',
  },
  searchWrap: {
    marginHorizontal: 20,
    marginBottom: 12,
    minHeight: 48,
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
    minHeight: 46,
    color: '#111827',
    fontSize: 15,
    fontWeight: '800',
  },
  clearSearchButton: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: '#F3F4F6',
    alignItems: 'center',
    justifyContent: 'center',
  },
  tableList: {
    paddingHorizontal: 18,
    paddingBottom: 28,
    gap: 14,
  },
  tableSection: {
    borderRadius: 22,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#EEF2F7',
    padding: 14,
  },
  tableSectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  tableSectionTitle: {
    fontSize: 17,
    fontWeight: '900',
    color: '#111827',
  },
  tableSectionSubtitle: {
    marginTop: 2,
    fontSize: 12,
    fontWeight: '700',
    color: '#6B7280',
  },
  countPill: {
    minWidth: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: '#F3F4F6',
    alignItems: 'center',
    justifyContent: 'center',
  },
  countText: {
    fontSize: 13,
    fontWeight: '900',
    color: '#111827',
  },
  tableGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  tableCard: {
    width: '48%',
    minHeight: 132,
    borderRadius: 18,
    backgroundColor: '#F8FAFC',
    padding: 16,
    borderWidth: 1,
    borderColor: '#EEF2F7',
  },
  statusDot: {
    width: 12,
    height: 12,
    borderRadius: 6,
    marginBottom: 12,
  },
  tableLabel: {
    fontSize: 21,
    fontWeight: '900',
    color: '#111827',
  },
  zoneName: {
    marginTop: 3,
    fontSize: 12,
    fontWeight: '800',
    color: '#6B7280',
  },
  tableStatus: {
    marginTop: 6,
    fontSize: 13,
    fontWeight: '900',
  },
  customerName: {
    marginTop: 8,
    fontSize: 13,
    color: '#4B5563',
    fontWeight: '700',
  },
  waiterName: {
    marginTop: 4,
    fontSize: 12,
    color: '#6B7280',
    fontWeight: '800',
  },
  tableTotal: {
    marginTop: 8,
    fontSize: 16,
    fontWeight: '900',
    color: '#111827',
  },
  sectionEmptyText: {
    paddingVertical: 10,
    color: '#9CA3AF',
    fontWeight: '700',
  },
  emptyState: {
    margin: 20,
    borderRadius: 20,
    padding: 24,
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
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
    color: '#6B7280',
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(17, 24, 39, 0.45)',
    justifyContent: 'center',
    padding: 20,
  },
  modalCard: {
    borderRadius: 22,
    backgroundColor: '#FFFFFF',
    padding: 20,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '900',
    color: '#111827',
  },
  modalText: {
    marginTop: 8,
    fontSize: 14,
    lineHeight: 20,
    color: '#6B7280',
  },
  input: {
    marginTop: 16,
    minHeight: 50,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#D1D5DB',
    paddingHorizontal: 14,
    color: '#111827',
    fontWeight: '800',
    backgroundColor: '#F9FAFB',
  },
  modalActions: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 18,
  },
  secondaryAction: {
    flex: 1,
    minHeight: 50,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#D1D5DB',
    alignItems: 'center',
    justifyContent: 'center',
  },
  secondaryActionText: {
    fontWeight: '900',
    color: '#111827',
  },
  primaryAction: {
    flex: 1,
    minHeight: 50,
    borderRadius: 14,
    backgroundColor: '#111827',
    alignItems: 'center',
    justifyContent: 'center',
  },
  primaryActionText: {
    fontWeight: '900',
    color: '#FFFFFF',
  },
});
