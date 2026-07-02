import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Modal,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
  useWindowDimensions,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { CameraView, useCameraPermissions, type BarcodeScanningResult } from 'expo-camera';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import type { ExitPass, Sucursal } from '@amare/types';
import {
  completeHostessReleaseOrder,
  completeHostessReservation,
  getHostessBranches,
  getHostessReleaseOrders,
  getHostessReservations,
  type HostessReleaseOrder,
  type HostessReservation,
  type HostessReservationStatus,
} from '../../services/hostess.service';
import { scanExitPass } from '../../services/orders.service';
import { getApiError } from '../../services/api';
import { useUserStore } from '../../store/user.store';
import { Colors, Shadows } from '../../theme';

type Filter = 'activas' | 'todas' | 'completadas';

export default function HostessDashboardScreen() {
  const { width } = useWindowDimensions();
  const [permission, requestPermission] = useCameraPermissions();
  const [scannerVisible, setScannerVisible] = useState(false);
  const [scanLocked, setScanLocked] = useState(false);
  const [isScanning, setIsScanning] = useState(false);
  const [lastPass, setLastPass] = useState<ExitPass | null>(null);
  const [lastValidationAt, setLastValidationAt] = useState<string | null>(null);
  const [validatedCount, setValidatedCount] = useState(0);
  const [branches, setBranches] = useState<Sucursal[]>([]);
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);
  const [reservations, setReservations] = useState<HostessReservation[]>([]);
  const [releaseOrders, setReleaseOrders] = useState<HostessReleaseOrder[]>([]);
  const [selectedReservation, setSelectedReservation] = useState<HostessReservation | null>(null);
  const [filter, setFilter] = useState<Filter>('activas');
  const [loadingReservations, setLoadingReservations] = useState(false);
  const [loadingReleaseOrders, setLoadingReleaseOrders] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [completingId, setCompletingId] = useState<number | null>(null);
  const [completingOrderId, setCompletingOrderId] = useState<number | null>(null);
  const scanLockedRef = useRef(false);
  const logout = useUserStore((state) => state.logout);
  const user = useUserStore((state) => state.user);

  const selectedBranch = useMemo(
    () => branches.find((branch) => Number(branch.id) === Number(selectedBranchId)) ?? null,
    [branches, selectedBranchId]
  );

  const operatorName = useMemo(() => {
    const name = typeof user?.nombre === 'string' ? user.nombre.trim() : '';
    return name !== '' ? name.split(/\s+/)[0] : 'Hostess';
  }, [user?.nombre]);

  const todayLabel = useMemo(
    () =>
      new Intl.DateTimeFormat('es-MX', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
      }).format(new Date()),
    []
  );

  const lastValidationTime = useMemo(() => {
    if (!lastValidationAt) return 'Sin registros';
    return formatTime(lastValidationAt);
  }, [lastValidationAt]);

  const activeReservations = useMemo(
    () => reservations.filter((item) => item.estado === 'pendiente' || item.estado === 'confirmada'),
    [reservations]
  );

  const completedReservations = useMemo(
    () => reservations.filter((item) => item.estado === 'completada'),
    [reservations]
  );

  const visibleReservations = useMemo(() => {
    if (filter === 'activas') return activeReservations;
    if (filter === 'completadas') return completedReservations;
    return reservations;
  }, [activeReservations, completedReservations, filter, reservations]);

  const nextReservation = activeReservations[0] ?? null;
  const pickupOrders = useMemo(
    () => releaseOrders.filter((order) => order.tipo_pedido === 'pickup'),
    [releaseOrders]
  );
  const deliveryOrders = useMemo(
    () => releaseOrders.filter((order) => order.tipo_pedido === 'delivery'),
    [releaseOrders]
  );
  const isTablet = width >= 700;
  const cameraStatusColor = permission?.granted ? Colors.success : Colors.warning;

  const loadBranches = useCallback(async () => {
    try {
      const list = await getHostessBranches();
      setBranches(list);
      setSelectedBranchId((current) => current ?? list[0]?.id ?? null);
    } catch (error) {
      Alert.alert('No pudimos cargar sucursales', getApiError(error));
    }
  }, []);

  const loadReservations = useCallback(async (branchId: number) => {
    setLoadingReservations(true);
    try {
      const list = await getHostessReservations(branchId);
      setReservations(list);
    } catch (error) {
      Alert.alert('No pudimos cargar reservaciones', getApiError(error));
    } finally {
      setLoadingReservations(false);
    }
  }, []);

  const loadReleaseOrders = useCallback(async (branchId: number) => {
    setLoadingReleaseOrders(true);
    try {
      const list = await getHostessReleaseOrders(branchId);
      setReleaseOrders(list);
    } catch (error) {
      Alert.alert('No pudimos cargar pedidos', getApiError(error));
    } finally {
      setLoadingReleaseOrders(false);
    }
  }, []);

  useEffect(() => {
    void loadBranches();
  }, [loadBranches]);

  useEffect(() => {
    if (selectedBranchId) {
      void loadReservations(selectedBranchId);
      void loadReleaseOrders(selectedBranchId);
    } else {
      setReservations([]);
      setReleaseOrders([]);
    }
  }, [loadReleaseOrders, loadReservations, selectedBranchId]);

  function unlockScanner() {
    scanLockedRef.current = false;
    setScanLocked(false);
    setIsScanning(false);
  }

  async function onRefresh() {
    setRefreshing(true);
    try {
      await loadBranches();
      if (selectedBranchId) {
        await Promise.all([
          loadReservations(selectedBranchId),
          loadReleaseOrders(selectedBranchId),
        ]);
      }
    } finally {
      setRefreshing(false);
    }
  }

  async function handleStartScanner() {
    if (!permission?.granted) {
      const result = await requestPermission();
      if (!result.granted) {
        Alert.alert('Permiso requerido', 'Necesitamos la cámara para validar QRs de salida.');
        return;
      }
    }
    setScannerVisible(true);
    unlockScanner();
  }

  async function handleBarcodeScanned(result: BarcodeScanningResult) {
    if (scanLockedRef.current || scanLocked || isScanning) return;
    const payload = result.data?.trim();
    if (!payload) return;

    scanLockedRef.current = true;
    setScanLocked(true);
    setIsScanning(true);

    try {
      const exitPass = await scanExitPass(payload);
      setLastPass(exitPass);
      setLastValidationAt(new Date().toISOString());
      setValidatedCount((current) => current + 1);
      setScannerVisible(false);
      Alert.alert(
        'Salida validada',
        `Pedido ${exitPass.folio ?? exitPass.pedido_id} liberado correctamente.`,
        [{ text: 'Escanear otro', onPress: handleStartScanner }, { text: 'Listo' }]
      );
    } catch (error) {
      Alert.alert('QR invalido', getApiError(error) || 'No pudimos validar este pase de salida.', [
        { text: 'Intentar de nuevo', onPress: unlockScanner },
      ]);
    } finally {
      setIsScanning(false);
    }
  }

  async function handleCompleteReservation(reservation: HostessReservation) {
    if (!selectedBranchId || reservation.estado === 'completada') return;

    setCompletingId(reservation.id);
    try {
      const updated = await completeHostessReservation(reservation.id, selectedBranchId);
      setReservations((current) => current.map((item) => (item.id === updated.id ? updated : item)));
      setSelectedReservation(updated);
    } catch (error) {
      Alert.alert('No se pudo completar', getApiError(error));
    } finally {
      setCompletingId(null);
    }
  }

  async function handleCompleteReleaseOrder(order: HostessReleaseOrder) {
    if (!selectedBranchId) return;

    setCompletingOrderId(order.id);
    try {
      await completeHostessReleaseOrder(order.id, selectedBranchId);
      setReleaseOrders((current) => current.filter((item) => item.id !== order.id));
    } catch (error) {
      Alert.alert('No se pudo completar el pedido', getApiError(error));
    } finally {
      setCompletingOrderId(null);
    }
  }

  if (scannerVisible) {
    return (
      <SafeAreaView style={styles.scannerSafe}>
        <View style={styles.scannerHeader}>
          <TouchableOpacity onPress={() => setScannerVisible(false)} style={styles.scannerIconButton}>
            <Ionicons name="close" size={22} color="#FFFFFF" />
          </TouchableOpacity>
          <View style={styles.scannerHeaderCopy}>
            <Text style={styles.scannerKicker}>Validacion de salida</Text>
            <Text style={styles.scannerTitle}>Escaner QR</Text>
          </View>
          <View style={styles.scannerIconButtonGhost}>
            <Ionicons name="scan-outline" size={22} color="#D1D5DB" />
          </View>
        </View>

        <View style={styles.cameraWrap}>
          <CameraView
            style={styles.camera}
            facing="back"
            barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
            onBarcodeScanned={scanLocked ? undefined : handleBarcodeScanned}
          />
          <View style={styles.scanOverlay} pointerEvents="none">
            <View style={styles.scanShadeTop} />
            <View style={styles.scanMiddle}>
              <View style={styles.scanShadeSide} />
              <View style={styles.scanFrame}>
                <View style={[styles.scanCorner, styles.scanCornerTopLeft]} />
                <View style={[styles.scanCorner, styles.scanCornerTopRight]} />
                <View style={[styles.scanCorner, styles.scanCornerBottomLeft]} />
                <View style={[styles.scanCorner, styles.scanCornerBottomRight]} />
              </View>
              <View style={styles.scanShadeSide} />
            </View>
            <View style={styles.scanShadeBottom} />
          </View>
        </View>

        <View style={styles.scannerFooter}>
          <View style={styles.scannerStatusRow}>
            <View style={[styles.statusDot, { backgroundColor: isScanning ? Colors.warning : Colors.success }]} />
            <Text style={styles.scannerFooterTitle}>{isScanning ? 'Validando pase' : 'Listo para escanear'}</Text>
          </View>
          <Text style={styles.scannerFooterText}>Alinea el QR del comensal dentro del marco.</Text>
          {isScanning ? (
            <View style={styles.processingRow}>
              <ActivityIndicator color={Colors.primary} />
              <Text style={styles.processingText}>Consultando salida...</Text>
            </View>
          ) : null}
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safe}>
      <ScrollView
        contentContainerStyle={[styles.content, isTablet && styles.contentTablet]}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void onRefresh()} />}
        showsVerticalScrollIndicator={false}
      >
        <LinearGradient
          colors={[Colors.primaryDark || '#12122A', Colors.primary || '#1A1A2E']}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={styles.headerPanel}
        >
          <View style={styles.headerTop}>
            <View style={styles.operatorBadge}>
              <Ionicons name="person-circle-outline" size={18} color="#FFFFFF" />
              <Text style={styles.operatorBadgeText}>{operatorName}</Text>
            </View>
            <TouchableOpacity onPress={() => void logout()} style={styles.logoutButton}>
              <Ionicons name="log-out-outline" size={21} color="#FFFFFF" />
            </TouchableOpacity>
          </View>

          <View style={styles.headerMain}>
            <View style={styles.headerCopy}>
              <Text style={styles.eyebrow}>Hostess</Text>
              <Text style={styles.title}>Recepción</Text>
              <Text style={styles.branchName} numberOfLines={1}>
                {selectedBranch?.nombre ?? 'Selecciona sucursal'}
              </Text>
            </View>
            <View style={styles.datePill}>
              <Ionicons name="calendar-outline" size={15} color="#F9FAFB" />
              <Text style={styles.datePillText}>{todayLabel}</Text>
            </View>
          </View>

          <View style={styles.headerStats}>
            <HeaderStat icon="bag-check-outline" label="Pedidos" value={String(releaseOrders.length)} />
            <HeaderStat icon="calendar-number-outline" label="Reservas" value={String(activeReservations.length)} />
            <HeaderStat icon="scan-outline" label="QR" value={permission?.granted ? 'Listo' : 'Permiso'} accentColor={cameraStatusColor} />
          </View>
        </LinearGradient>

        <TouchableOpacity style={styles.primaryAction} onPress={handleStartScanner} activeOpacity={0.9}>
          <View style={styles.primaryIcon}>
            <Ionicons name="qr-code-outline" size={28} color="#FFFFFF" />
          </View>
          <View style={styles.primaryActionText}>
            <Text style={styles.primaryActionTitle}>Validar salida</Text>
            <Text style={styles.primaryActionSubtitle}>Escanear pase QR de mesa</Text>
          </View>
          <View style={styles.primaryArrow}>
            <Ionicons name="chevron-forward" size={22} color={Colors.primary} />
          </View>
        </TouchableOpacity>

        <View style={[styles.section, isTablet && styles.sectionTablet]}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Pedidos para liberar</Text>
            <TouchableOpacity disabled={!selectedBranchId || loadingReleaseOrders} onPress={() => selectedBranchId && void loadReleaseOrders(selectedBranchId)} style={styles.iconButton}>
              {loadingReleaseOrders ? <ActivityIndicator size="small" color={Colors.primary} /> : <Ionicons name="refresh" size={18} color={Colors.primary} />}
            </TouchableOpacity>
          </View>
          <View style={styles.releaseSummary}>
            <ReleaseSummaryCard icon="bag-handle-outline" title="Pickup" count={pickupOrders.length} />
            <ReleaseSummaryCard icon="bicycle-outline" title="Delivery" count={deliveryOrders.length} />
          </View>
          {loadingReleaseOrders && releaseOrders.length === 0 ? (
            <View style={styles.loadingBlock}>
              <ActivityIndicator color={Colors.primary} />
              <Text style={styles.loadingText}>Cargando pedidos...</Text>
            </View>
          ) : null}
          {!loadingReleaseOrders && releaseOrders.length === 0 ? (
            <EmptyState icon="bag-check-outline" title="Sin pedidos activos" text="Pickup y delivery apareceran aqui cuando esten en curso." />
          ) : null}
          {releaseOrders.map((order) => (
            <ReleaseOrderRow
              key={order.id}
              order={order}
              completing={completingOrderId === order.id}
              onComplete={() => void handleCompleteReleaseOrder(order)}
            />
          ))}
        </View>

        <View style={[styles.section, isTablet && styles.sectionTablet]}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Reservaciones</Text>
            <TouchableOpacity disabled={!selectedBranchId || loadingReservations} onPress={() => selectedBranchId && void loadReservations(selectedBranchId)} style={styles.iconButton}>
              {loadingReservations ? <ActivityIndicator size="small" color={Colors.primary} /> : <Ionicons name="refresh" size={18} color={Colors.primary} />}
            </TouchableOpacity>
          </View>

          <View style={styles.filterRow}>
            <FilterButton label="Activas" selected={filter === 'activas'} onPress={() => setFilter('activas')} />
            <FilterButton label="Todas" selected={filter === 'todas'} onPress={() => setFilter('todas')} />
            <FilterButton label="Completadas" selected={filter === 'completadas'} onPress={() => setFilter('completadas')} />
          </View>

          {nextReservation ? (
            <View style={styles.nextCard}>
              <View style={styles.nextIcon}>
                <Ionicons name="time-outline" size={22} color={Colors.primary} />
              </View>
              <View style={styles.nextCopy}>
                <Text style={styles.nextLabel}>Siguiente</Text>
                <Text style={styles.nextTitle} numberOfLines={1}>{nextReservation.nombre}</Text>
                <Text style={styles.nextMeta}>{formatDateTime(nextReservation)} - {nextReservation.personas} personas</Text>
              </View>
            </View>
          ) : null}

          {loadingReservations && reservations.length === 0 ? (
            <View style={styles.loadingBlock}>
              <ActivityIndicator color={Colors.primary} />
              <Text style={styles.loadingText}>Cargando reservaciones...</Text>
            </View>
          ) : null}

          {!loadingReservations && visibleReservations.length === 0 ? (
            <EmptyState icon="calendar-clear-outline" title="Sin reservaciones" text="No hay reservaciones en este filtro." />
          ) : null}

          {visibleReservations.map((reservation) => (
            <ReservationRow
              key={reservation.id}
              reservation={reservation}
              completing={completingId === reservation.id}
              onOpen={() => setSelectedReservation(reservation)}
              onComplete={() => void handleCompleteReservation(reservation)}
            />
          ))}
        </View>

        <View style={[styles.section, isTablet && styles.sectionTablet]}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Ultima salida</Text>
            <Ionicons name={lastPass ? 'checkmark-circle' : 'remove-circle-outline'} size={18} color={lastPass ? Colors.success : Colors.textMuted} />
          </View>
          {lastPass ? (
            <View style={styles.lastPassCard}>
              <View style={styles.lastPassIcon}>
                <Ionicons name="receipt-outline" size={22} color={Colors.primary} />
              </View>
              <View style={styles.lastPassCopy}>
                <Text style={styles.lastPassTitle}>{lastPass.folio ?? `Pedido ${lastPass.pedido_id}`}</Text>
                <Text style={styles.lastPassMeta}>
                  {lastPass.mesa_id ? `Mesa ${lastPass.mesa_id}` : 'Mesa validada'} - {lastValidationTime}
                </Text>
              </View>
              <View style={styles.validatedBadge}>
                <Text style={styles.validatedBadgeText}>Liberada</Text>
              </View>
            </View>
          ) : (
            <EmptyState icon="sparkles-outline" title="Sin salidas validadas" text="El ultimo pase escaneado aparecera aqui." />
          )}
        </View>
      </ScrollView>

      <ReservationModal
        reservation={selectedReservation}
        completing={selectedReservation ? completingId === selectedReservation.id : false}
        onClose={() => setSelectedReservation(null)}
        onComplete={(reservation) => void handleCompleteReservation(reservation)}
      />
    </SafeAreaView>
  );
}

function ReservationRow({
  reservation,
  completing,
  onOpen,
  onComplete,
}: {
  reservation: HostessReservation;
  completing: boolean;
  onOpen: () => void;
  onComplete: () => void;
}) {
  const completed = reservation.estado === 'completada';
  const cancelled = reservation.estado === 'cancelada';

  return (
    <TouchableOpacity style={styles.reservationRow} onPress={onOpen} activeOpacity={0.86}>
      <View style={styles.reservationTime}>
        <Text style={styles.reservationHour}>{formatReservationTime(reservation.hora)}</Text>
        <Text style={styles.reservationDate}>{formatShortDate(reservation.fecha)}</Text>
      </View>
      <View style={styles.reservationCopy}>
        <View style={styles.reservationTitleRow}>
          <Text style={styles.reservationName} numberOfLines={1}>{reservation.nombre}</Text>
          <StatusBadge status={reservation.estado} />
        </View>
        <Text style={styles.reservationMeta} numberOfLines={1}>
          {reservation.mesa_label ?? 'Sin mesa'} - {reservation.personas} personas
        </Text>
        <Text style={styles.reservationMeta} numberOfLines={1}>
          {reservation.telefono || reservation.email || 'Sin contacto'}
        </Text>
      </View>
      {!completed && !cancelled ? (
        <TouchableOpacity style={styles.completeButton} onPress={onComplete} disabled={completing}>
          {completing ? <ActivityIndicator size="small" color="#FFFFFF" /> : <Ionicons name="checkmark" size={18} color="#FFFFFF" />}
        </TouchableOpacity>
      ) : (
        <View style={styles.completeButtonGhost}>
          <Ionicons name={completed ? 'checkmark-done' : 'close'} size={18} color={completed ? Colors.success : Colors.textMuted} />
        </View>
      )}
    </TouchableOpacity>
  );
}

function ReleaseSummaryCard({
  icon,
  title,
  count,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  title: string;
  count: number;
}) {
  return (
    <View style={styles.releaseSummaryCard}>
      <View style={styles.releaseSummaryIcon}>
        <Ionicons name={icon} size={21} color={Colors.primary} />
      </View>
      <View style={styles.releaseSummaryCopy}>
        <Text style={styles.releaseSummaryTitle}>{title}</Text>
        <Text style={styles.releaseSummaryText}>{count} activos</Text>
      </View>
      <View style={[styles.releaseCount, count > 0 && styles.releaseCountActive]}>
        <Text style={[styles.releaseCountText, count > 0 && styles.releaseCountTextActive]}>{count}</Text>
      </View>
    </View>
  );
}

function ReleaseOrderRow({
  order,
  completing,
  onComplete,
}: {
  order: HostessReleaseOrder;
  completing: boolean;
  onComplete: () => void;
}) {
  const typeLabel = order.tipo_pedido === 'delivery' ? 'Delivery' : 'Pickup';
  const time = order.created_at ? formatTime(order.created_at) : '--:--';

  return (
    <View style={styles.releaseOrderRow}>
      <View style={styles.releaseOrderIcon}>
        <Ionicons name={order.tipo_pedido === 'delivery' ? 'bicycle-outline' : 'bag-handle-outline'} size={21} color={Colors.primary} />
      </View>
      <View style={styles.releaseOrderCopy}>
        <View style={styles.reservationTitleRow}>
          <Text style={styles.releaseOrderTitle} numberOfLines={1}>{order.folio ?? `Pedido ${order.id}`}</Text>
          <View style={styles.releaseTypeBadge}>
            <Text style={styles.releaseTypeBadgeText}>{typeLabel}</Text>
          </View>
        </View>
        <Text style={styles.releaseOrderMeta} numberOfLines={1}>
          {order.cliente_nombre || 'Cliente app'} - {order.items_count} productos - {time}
        </Text>
        <Text style={styles.releaseOrderMeta} numberOfLines={1}>
          {order.estado ?? 'pendiente'} - ${order.total.toFixed(2)}
        </Text>
      </View>
      <TouchableOpacity style={styles.completeButton} onPress={onComplete} disabled={completing}>
        {completing ? <ActivityIndicator size="small" color="#FFFFFF" /> : <Ionicons name="checkmark" size={18} color="#FFFFFF" />}
      </TouchableOpacity>
    </View>
  );
}

function ReservationModal({
  reservation,
  completing,
  onClose,
  onComplete,
}: {
  reservation: HostessReservation | null;
  completing: boolean;
  onClose: () => void;
  onComplete: (reservation: HostessReservation) => void;
}) {
  const canComplete = reservation && reservation.estado !== 'completada' && reservation.estado !== 'cancelada';

  return (
    <Modal visible={reservation !== null} transparent animationType="slide" onRequestClose={onClose}>
      <View style={styles.modalBackdrop}>
        <View style={styles.modalCard}>
          <View style={styles.modalHeader}>
            <View>
              <Text style={styles.modalKicker}>Reservacion</Text>
              <Text style={styles.modalTitle} numberOfLines={1}>{reservation?.nombre ?? ''}</Text>
            </View>
            <TouchableOpacity style={styles.modalClose} onPress={onClose}>
              <Ionicons name="close" size={20} color={Colors.text} />
            </TouchableOpacity>
          </View>

          {reservation ? (
            <>
              <StatusBadge status={reservation.estado} />
              <View style={styles.detailGrid}>
                <DetailItem icon="calendar-outline" label="Fecha" value={formatDateTime(reservation)} />
                <DetailItem icon="people-outline" label="Personas" value={String(reservation.personas)} />
                <DetailItem icon="restaurant-outline" label="Mesa" value={reservation.mesa_label ?? 'Sin mesa'} />
                <DetailItem icon="call-outline" label="Telefono" value={reservation.telefono || 'Sin telefono'} />
                <DetailItem icon="mail-outline" label="Email" value={reservation.email || 'Sin email'} />
                <DetailItem icon="globe-outline" label="Origen" value={reservation.origen || 'Sistema'} />
              </View>
              <View style={styles.notesBox}>
                <Text style={styles.notesLabel}>Notas</Text>
                <Text style={styles.notesText}>{reservation.notas || 'Sin notas'}</Text>
              </View>
              {canComplete ? (
                <TouchableOpacity style={styles.modalCompleteButton} onPress={() => onComplete(reservation)} disabled={completing}>
                  {completing ? <ActivityIndicator color="#FFFFFF" /> : <Ionicons name="checkmark-circle-outline" size={20} color="#FFFFFF" />}
                  <Text style={styles.modalCompleteText}>Marcar como completada</Text>
                </TouchableOpacity>
              ) : null}
            </>
          ) : null}
        </View>
      </View>
    </Modal>
  );
}

function HeaderStat({
  icon,
  label,
  value,
  accentColor,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  value: string;
  accentColor?: string;
}) {
  return (
    <View style={styles.headerStat}>
      <View style={styles.headerStatIcon}>
        <Ionicons name={icon} size={16} color={accentColor ?? '#FFFFFF'} />
      </View>
      <View style={styles.headerStatCopy}>
        <Text style={styles.headerStatLabel}>{label}</Text>
        <Text style={styles.headerStatValue} numberOfLines={1} adjustsFontSizeToFit>
          {value}
        </Text>
      </View>
    </View>
  );
}

function FilterButton({ label, selected, onPress }: { label: string; selected: boolean; onPress: () => void }) {
  return (
    <TouchableOpacity style={[styles.filterButton, selected && styles.filterButtonSelected]} onPress={onPress}>
      <Text style={[styles.filterButtonText, selected && styles.filterButtonTextSelected]}>{label}</Text>
    </TouchableOpacity>
  );
}

function StatusBadge({ status }: { status: HostessReservationStatus }) {
  const config = {
    pendiente: { label: 'Pendiente', color: Colors.warning, bg: '#FFF7E6' },
    confirmada: { label: 'Confirmada', color: Colors.info, bg: '#EAF4FF' },
    completada: { label: 'Completada', color: Colors.success, bg: '#ECFDF3' },
    cancelada: { label: 'Cancelada', color: Colors.error, bg: '#FEECEC' },
  }[status] ?? { label: status, color: Colors.textMuted, bg: '#F3F4F6' };

  return (
    <View style={[styles.statusBadge, { backgroundColor: config.bg }]}>
      <Text style={[styles.statusBadgeText, { color: config.color }]}>{config.label}</Text>
    </View>
  );
}

function DetailItem({ icon, label, value }: { icon: keyof typeof Ionicons.glyphMap; label: string; value: string }) {
  return (
    <View style={styles.detailItem}>
      <View style={styles.detailIcon}>
        <Ionicons name={icon} size={17} color={Colors.primary} />
      </View>
      <View style={styles.detailCopy}>
        <Text style={styles.detailLabel}>{label}</Text>
        <Text style={styles.detailValue} numberOfLines={2}>{value}</Text>
      </View>
    </View>
  );
}

function EmptyState({ icon, title, text }: { icon: keyof typeof Ionicons.glyphMap; title: string; text: string }) {
  return (
    <View style={styles.emptyState}>
      <Ionicons name={icon} size={24} color={Colors.textMuted} />
      <Text style={styles.emptyTitle}>{title}</Text>
      <Text style={styles.emptyText}>{text}</Text>
    </View>
  );
}

function formatDateTime(reservation: HostessReservation): string {
  const date = formatLongDate(reservation.fecha);
  const time = formatReservationTime(reservation.hora);
  return `${date} ${time}`;
}

function formatLongDate(value?: string | null): string {
  if (!value) return 'Sin fecha';
  const date = new Date(`${value}T12:00:00`);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
}

function formatShortDate(value?: string | null): string {
  if (!value) return '--';
  const date = new Date(`${value}T12:00:00`);
  if (Number.isNaN(date.getTime())) return value.slice(5);
  return new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short' }).format(date);
}

function formatReservationTime(value?: string | null): string {
  if (!value) return '--:--';
  return value.slice(0, 5);
}

function formatTime(value: string): string {
  return new Intl.DateTimeFormat('es-MX', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: '#F4F6F8',
  },
  content: {
    padding: 16,
    gap: 14,
    paddingBottom: 30,
  },
  contentTablet: {
    width: '100%',
    maxWidth: 760,
    alignSelf: 'center',
  },
  headerPanel: {
    borderRadius: 8,
    padding: 18,
    gap: 16,
    shadowColor: '#111827',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.12,
    shadowRadius: 22,
    elevation: 8,
  },
  headerTop: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  operatorBadge: {
    minHeight: 32,
    borderRadius: 8,
    paddingHorizontal: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
    backgroundColor: 'rgba(255,255,255,0.14)',
  },
  operatorBadgeText: {
    color: '#FFFFFF',
    fontSize: 13,
    fontWeight: '800',
  },
  logoutButton: {
    width: 36,
    height: 36,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.13)',
  },
  headerMain: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 14,
  },
  headerCopy: {
    flex: 1,
  },
  eyebrow: {
    color: Colors.accentLight || '#F5C060',
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  title: {
    color: '#FFFFFF',
    fontSize: 28,
    fontWeight: '900',
    marginTop: 1,
  },
  branchName: {
    color: '#E5E7EB',
    fontSize: 13,
    fontWeight: '800',
    marginTop: 4,
  },
  datePill: {
    minHeight: 32,
    borderRadius: 8,
    paddingHorizontal: 9,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: 'rgba(255,255,255,0.12)',
  },
  datePillText: {
    color: '#F9FAFB',
    fontSize: 12,
    fontWeight: '800',
    textTransform: 'capitalize',
  },
  headerStats: {
    flexDirection: 'row',
    gap: 8,
  },
  headerStat: {
    flex: 1,
    minHeight: 54,
    borderRadius: 8,
    paddingHorizontal: 9,
    paddingVertical: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
    backgroundColor: 'rgba(255,255,255,0.13)',
  },
  headerStatIcon: {
    width: 28,
    height: 28,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.13)',
  },
  headerStatCopy: {
    flex: 1,
    minWidth: 0,
  },
  headerStatLabel: {
    color: '#CBD5E1',
    fontSize: 9,
    fontWeight: '800',
    textTransform: 'uppercase',
  },
  headerStatValue: {
    color: '#FFFFFF',
    fontSize: 14,
    fontWeight: '900',
    marginTop: 1,
  },
  primaryAction: {
    minHeight: 84,
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E1E7EF',
    padding: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    shadowColor: '#111827',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.06,
    shadowRadius: 14,
    elevation: 3,
  },
  primaryIcon: {
    width: 50,
    height: 50,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.primary,
  },
  primaryActionText: {
    flex: 1,
  },
  primaryActionTitle: {
    color: Colors.text,
    fontSize: 19,
    fontWeight: '900',
  },
  primaryActionSubtitle: {
    color: Colors.textSecondary,
    fontSize: 13,
    fontWeight: '700',
    marginTop: 3,
  },
  primaryArrow: {
    width: 34,
    height: 34,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F4F7FB',
  },
  section: {
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E1E7EF',
    padding: 14,
    gap: 12,
    shadowColor: '#111827',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.04,
    shadowRadius: 10,
    elevation: 2,
  },
  sectionTablet: {
    padding: 16,
  },
  sectionHeader: {
    minHeight: 28,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  sectionTitle: {
    color: Colors.text,
    fontSize: 17,
    fontWeight: '900',
  },
  iconButton: {
    width: 34,
    height: 34,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F4F7FB',
  },
  releaseSummary: {
    flexDirection: 'row',
    gap: 8,
  },
  releaseSummaryCard: {
    flex: 1,
    minHeight: 64,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#E1E7EF',
    backgroundColor: '#F7FAFC',
    padding: 9,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 9,
  },
  releaseSummaryIcon: {
    width: 34,
    height: 34,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#FFFFFF',
  },
  releaseSummaryCopy: {
    flex: 1,
  },
  releaseSummaryTitle: {
    color: Colors.text,
    fontSize: 12,
    fontWeight: '900',
  },
  releaseSummaryText: {
    color: Colors.textSecondary,
    fontSize: 10,
    fontWeight: '800',
    marginTop: 2,
  },
  releaseCount: {
    minWidth: 30,
    height: 30,
    borderRadius: 8,
    paddingHorizontal: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#EEF0F4',
  },
  releaseCountActive: {
    backgroundColor: Colors.primary,
  },
  releaseCountText: {
    color: Colors.textSecondary,
    fontSize: 12,
    fontWeight: '900',
  },
  releaseCountTextActive: {
    color: '#FFFFFF',
  },
  releaseOrderRow: {
    minHeight: 76,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: '#E5E7EB',
  },
  releaseOrderIcon: {
    width: 40,
    height: 40,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F4F7FB',
  },
  releaseOrderCopy: {
    flex: 1,
    gap: 3,
  },
  releaseOrderTitle: {
    flex: 1,
    color: Colors.text,
    fontSize: 14,
    fontWeight: '900',
  },
  releaseOrderMeta: {
    color: Colors.textSecondary,
    fontSize: 11,
    fontWeight: '700',
  },
  releaseTypeBadge: {
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 5,
    backgroundColor: '#EEF2FF',
  },
  releaseTypeBadgeText: {
    color: Colors.primary,
    fontSize: 10,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  filterRow: {
    flexDirection: 'row',
    gap: 6,
  },
  filterButton: {
    flex: 1,
    minHeight: 34,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F3F4F6',
  },
  filterButtonSelected: {
    backgroundColor: Colors.primary,
  },
  filterButtonText: {
    color: Colors.textSecondary,
    fontSize: 11,
    fontWeight: '900',
  },
  filterButtonTextSelected: {
    color: '#FFFFFF',
  },
  nextCard: {
    minHeight: 68,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#FFE1D8',
    backgroundColor: '#FFF7F4',
    padding: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  nextIcon: {
    width: 40,
    height: 40,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#FFFFFF',
  },
  nextCopy: {
    flex: 1,
  },
  nextLabel: {
    color: Colors.primary,
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  nextTitle: {
    color: Colors.text,
    fontSize: 15,
    fontWeight: '900',
    marginTop: 2,
  },
  nextMeta: {
    color: Colors.textSecondary,
    fontSize: 11,
    fontWeight: '700',
    marginTop: 2,
  },
  loadingBlock: {
    minHeight: 96,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  loadingText: {
    color: Colors.textSecondary,
    fontSize: 13,
    fontWeight: '800',
  },
  reservationRow: {
    minHeight: 78,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: '#E5E7EB',
  },
  reservationTime: {
    width: 54,
    minHeight: 54,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F4F5F7',
  },
  reservationHour: {
    color: Colors.text,
    fontSize: 14,
    fontWeight: '900',
  },
  reservationDate: {
    color: Colors.textSecondary,
    fontSize: 9,
    fontWeight: '800',
    marginTop: 2,
    textTransform: 'capitalize',
  },
  reservationCopy: {
    flex: 1,
    gap: 3,
  },
  reservationTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  reservationName: {
    flex: 1,
    color: Colors.text,
    fontSize: 14,
    fontWeight: '900',
  },
  reservationMeta: {
    color: Colors.textSecondary,
    fontSize: 11,
    fontWeight: '700',
  },
  statusBadge: {
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 5,
  },
  statusBadgeText: {
    fontSize: 10,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  completeButton: {
    width: 40,
    height: 40,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.success,
  },
  completeButtonGhost: {
    width: 40,
    height: 40,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F3F4F6',
  },
  lastPassCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingTop: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: '#E5E7EB',
  },
  lastPassIcon: {
    width: 42,
    height: 42,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F3F4F6',
  },
  lastPassCopy: {
    flex: 1,
  },
  lastPassTitle: {
    color: Colors.text,
    fontSize: 15,
    fontWeight: '900',
  },
  lastPassMeta: {
    color: Colors.textSecondary,
    fontSize: 13,
    fontWeight: '700',
    marginTop: 2,
  },
  validatedBadge: {
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 7,
    backgroundColor: Colors.successLight,
  },
  validatedBadgeText: {
    color: '#047857',
    fontSize: 12,
    fontWeight: '900',
  },
  emptyState: {
    borderRadius: 8,
    backgroundColor: '#F8FAFC',
    borderWidth: 1,
    borderColor: '#EEF0F4',
    padding: 16,
    alignItems: 'center',
    gap: 5,
  },
  emptyTitle: {
    color: Colors.text,
    fontSize: 15,
    fontWeight: '900',
  },
  emptyText: {
    color: Colors.textSecondary,
    fontSize: 13,
    fontWeight: '700',
    textAlign: 'center',
  },
  modalBackdrop: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(0,0,0,0.38)',
  },
  modalCard: {
    borderTopLeftRadius: 8,
    borderTopRightRadius: 8,
    backgroundColor: '#FFFFFF',
    padding: 18,
    gap: 14,
    maxHeight: '86%',
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 14,
  },
  modalKicker: {
    color: Colors.textSecondary,
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  modalTitle: {
    color: Colors.text,
    fontSize: 23,
    fontWeight: '900',
    marginTop: 2,
  },
  modalClose: {
    width: 38,
    height: 38,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F3F4F6',
  },
  detailGrid: {
    gap: 10,
  },
  detailItem: {
    minHeight: 54,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderRadius: 8,
    backgroundColor: '#F8FAFC',
    padding: 10,
  },
  detailIcon: {
    width: 34,
    height: 34,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#FFFFFF',
  },
  detailCopy: {
    flex: 1,
  },
  detailLabel: {
    color: Colors.textSecondary,
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  detailValue: {
    color: Colors.text,
    fontSize: 14,
    fontWeight: '800',
    marginTop: 2,
  },
  notesBox: {
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 12,
    gap: 5,
  },
  notesLabel: {
    color: Colors.textSecondary,
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  notesText: {
    color: Colors.text,
    fontSize: 14,
    fontWeight: '700',
    lineHeight: 20,
  },
  modalCompleteButton: {
    minHeight: 50,
    borderRadius: 8,
    backgroundColor: Colors.success,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  modalCompleteText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '900',
  },
  scannerSafe: {
    flex: 1,
    backgroundColor: '#090B13',
  },
  scannerHeader: {
    paddingHorizontal: 16,
    paddingVertical: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  scannerIconButton: {
    width: 42,
    height: 42,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.14)',
  },
  scannerIconButtonGhost: {
    width: 42,
    height: 42,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  scannerHeaderCopy: {
    alignItems: 'center',
  },
  scannerKicker: {
    color: '#A7B0C0',
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  scannerTitle: {
    color: '#FFFFFF',
    fontSize: 18,
    fontWeight: '900',
    marginTop: 1,
  },
  cameraWrap: {
    flex: 1,
    overflow: 'hidden',
  },
  camera: {
    flex: 1,
  },
  scanOverlay: {
    ...StyleSheet.absoluteFillObject,
  },
  scanShadeTop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.46)',
  },
  scanMiddle: {
    height: 276,
    flexDirection: 'row',
  },
  scanShadeSide: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.46)',
  },
  scanShadeBottom: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.46)',
  },
  scanFrame: {
    width: 276,
    height: 276,
    position: 'relative',
  },
  scanCorner: {
    position: 'absolute',
    width: 48,
    height: 48,
    borderColor: '#FFFFFF',
  },
  scanCornerTopLeft: {
    top: 0,
    left: 0,
    borderTopWidth: 4,
    borderLeftWidth: 4,
    borderTopLeftRadius: 8,
  },
  scanCornerTopRight: {
    top: 0,
    right: 0,
    borderTopWidth: 4,
    borderRightWidth: 4,
    borderTopRightRadius: 8,
  },
  scanCornerBottomLeft: {
    bottom: 0,
    left: 0,
    borderBottomWidth: 4,
    borderLeftWidth: 4,
    borderBottomLeftRadius: 8,
  },
  scanCornerBottomRight: {
    bottom: 0,
    right: 0,
    borderBottomWidth: 4,
    borderRightWidth: 4,
    borderBottomRightRadius: 8,
  },
  scannerFooter: {
    backgroundColor: '#FFFFFF',
    padding: 18,
    gap: 8,
  },
  scannerStatusRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 9,
  },
  statusDot: {
    width: 9,
    height: 9,
    borderRadius: 5,
  },
  scannerFooterTitle: {
    color: Colors.text,
    fontSize: 20,
    fontWeight: '900',
  },
  scannerFooterText: {
    color: Colors.textSecondary,
    fontSize: 14,
    fontWeight: '700',
  },
  processingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginTop: 8,
  },
  processingText: {
    color: Colors.textSecondary,
    fontSize: 13,
    fontWeight: '800',
  },
});
