import React, { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  type AlertButton,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { CameraView, useCameraPermissions, type BarcodeScanningResult } from 'expo-camera';
import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useQueryClient } from '@tanstack/react-query';
import type { TableScanResult } from '@amare/types';
import { getBranchById } from '../services/branches.service';
import { getTableSessionDiagnostic, resetTableSessionForTesting, scanTableQr } from '../services/table-session.service';
import { getApiError, getApiErrorCode } from '../services/api';
import { ensureCameraPermission } from '../services/app-permissions.service';
import { useBranchStore } from '../store/branch.store';
import { useCartStore } from '../store/cart.store';
import { useTableSessionStore } from '../store/table-session.store';
import { useUserStore } from '../store/user.store';
import { Colors, Spacing } from '../theme';

export default function TableScannerScreen() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const { returnTo, activateSocial, mode, branchId } = useLocalSearchParams<{
    returnTo?: string;
    activateSocial?: string;
    mode?: string;
    branchId?: string;
  }>();
  const [permission, requestPermission] = useCameraPermissions();
  const [isProcessing, setIsProcessing] = useState(false);
  const [scanLocked, setScanLocked] = useState(false);
  const [confirmedTable, setConfirmedTable] = useState<TableScanResult | null>(null);
  const scanLockedRef = useRef(false);
  const hasNavigatedRef = useRef(false);
  const tableAppliedRef = useRef(false);
  const successTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const seleccionar = useBranchStore((s) => s.seleccionar);
  const currentBranch = useBranchStore((s) => s.seleccionada);
  const sucursales = useBranchStore((s) => s.sucursales);
  const { itemCount, restauranteId: cartRestaurantId, clear, setTipoPedido } = useCartStore();
  const setTableSession = useTableSessionStore((s) => s.setSession);
  const deferScan = useTableSessionStore((s) => s.deferScan);
  const clearTableSession = useTableSessionStore((s) => s.clearSession);
  const updateProfile = useUserStore((s) => s.updateProfile);

  async function handleCameraPermissionRequest() {
    const result = await requestPermission();
    if (!result.granted && !result.canAskAgain) {
      await ensureCameraPermission({ explainIfBlocked: true });
    }
  }

  const destination = typeof returnTo === 'string' && returnTo.trim() ? returnTo : '/(tabs)';
  const resolvedDestination = destination === '/(tabs)/index' ? '/(tabs)' : destination;
  const confirmedBranchName = confirmedTable?.branch?.nombre ?? currentBranch?.nombre ?? 'Sucursal';

  useEffect(() => {
    return () => {
      if (successTimerRef.current) {
        clearTimeout(successTimerRef.current);
      }
    };
  }, []);

  function navigateToDestination(options?: { tableScanDeferred?: boolean }) {
    if (resolvedDestination === '/(tabs)' && options?.tableScanDeferred) {
      router.replace({ pathname: '/(tabs)', params: { tableScanDeferred: '1' } } as never);
      return;
    }

    if (resolvedDestination === '/(tabs)' && router.canGoBack()) {
      router.back();
      return;
    }

    router.replace(resolvedDestination as never);
  }

  function continueAfterScan() {
    if (successTimerRef.current) {
      clearTimeout(successTimerRef.current);
      successTimerRef.current = null;
    }

    if (hasNavigatedRef.current) return;
    hasNavigatedRef.current = true;

    if (activateSocial === '1' && resolvedDestination === '/profile/social') {
      router.replace({ pathname: '/profile/social', params: { activateSocial: '1' } } as never);
      return;
    }

    navigateToDestination();
  }

  function resetScanLock() {
    scanLockedRef.current = false;
    setScanLocked(false);
  }

  function humanizeBlockReason(reason: string): string {
    switch (reason) {
      case 'cuenta_abierta':
        return 'Cuenta abierta';
      case 'salida_qr_pendiente_validacion':
        return 'QR de salida pendiente';
      case 'pedido_activo':
        return 'Pedido activo';
      default:
        return reason.replace(/_/g, ' ');
    }
  }

  async function handleResetTestingSession() {
    setIsProcessing(true);
    try {
      const result = await resetTableSessionForTesting();
      clearTableSession();
      updateProfile({
        current_restaurante_id: null,
        mesa: null,
        is_social_active: false,
        modo_social: false,
      });
      void queryClient.invalidateQueries({ queryKey: ['table-session', 'diagnostic'] });

      Alert.alert(
        'Cuenta de prueba liberada',
        result.affected_orders > 0
          ? 'Ya puedes escanear otra mesa para continuar probando.'
          : 'No habia una cuenta activa por liberar.',
        [{ text: 'Escanear', onPress: resetScanLock }]
      );
    } catch (error) {
      Alert.alert('No se pudo liberar', getApiError(error) || 'Intenta cerrar la cuenta manualmente.', [
        { text: 'Intentar de nuevo', onPress: resetScanLock },
      ]);
    } finally {
      setIsProcessing(false);
    }
  }

  async function showTableSessionActiveAlert(error: unknown) {
    const fallbackMessage = getApiError(error) || 'Primero cierra tu cuenta actual antes de cambiar de mesa.';
    const diagnostic = await getTableSessionDiagnostic().catch(() => null);
    const activeVisit = diagnostic?.active_visit ?? null;
    const orderId = activeVisit?.pedido_id ?? null;
    const detailLines = activeVisit
      ? [
          activeVisit.mesa_label ? `Mesa actual: ${activeVisit.mesa_label}` : null,
          activeVisit.folio ? `Cuenta: ${activeVisit.folio}` : null,
          typeof activeVisit.total === 'number' ? `Total: $${activeVisit.total.toFixed(2)} MXN` : null,
          activeVisit.block_reasons?.length
            ? `Pendiente: ${activeVisit.block_reasons.map(humanizeBlockReason).join(', ')}`
            : null,
        ].filter(Boolean)
      : [];
    const message = [diagnostic?.message || fallbackMessage, ...detailLines].join('\n\n');
    const buttons: AlertButton[] = [
      {
        text: 'Intentar de nuevo',
        onPress: resetScanLock,
      },
    ];

    if (orderId) {
      buttons.unshift({
        text: 'Ver cuenta',
        onPress: () => {
          hasNavigatedRef.current = true;
          router.push({ pathname: '/order/[id]', params: { id: String(orderId) } } as never);
        },
      });
    }

    if (__DEV__) {
      buttons.push({
        text: 'Liberar prueba',
        style: 'destructive',
        onPress: handleResetTestingSession,
      });
    }

    Alert.alert('Cuenta activa', message, buttons);
  }

  async function handleScanLater() {
    if (hasNavigatedRef.current) return;
    hasNavigatedRef.current = true;

    if (mode === 'eat_in') {
      clearTableSession();

      let selectedBranch = branchId
        ? sucursales.find((item) => String(item.id) === String(branchId))
        : null;

      if (!selectedBranch && branchId && Number(branchId) > 0) {
        selectedBranch = await getBranchById(Number(branchId)).catch(() => null);
      }

      const fallbackBranch =
        selectedBranch ??
        currentBranch ??
        sucursales.find((item) => item.tipos_entrega?.includes('eat_in')) ??
        sucursales[0] ??
        null;

      if (fallbackBranch) {
        seleccionar(fallbackBranch);
        deferScan(fallbackBranch);
        void queryClient.invalidateQueries({ queryKey: ['menu', fallbackBranch.id] });
      } else {
        deferScan(null);
      }

      setTipoPedido('eat_in');
    }

    navigateToDestination({ tableScanDeferred: mode === 'eat_in' });
  }

  async function handleBarcodeScanned(result: BarcodeScanningResult) {
    if (scanLockedRef.current || scanLocked || isProcessing) return;

    const payload = result.data?.trim();
    if (!payload) return;

    scanLockedRef.current = true;
    setScanLocked(true);
    setIsProcessing(true);

    try {
      if (__DEV__) {
        console.log('[TableScanner] QR payload:', payload);
      }
      const table = await scanTableQr(payload, null);
      handleResolvedTable(table);
    } catch (error) {
      if (getApiErrorCode(error) === 'TABLE_SESSION_ACTIVE') {
        await showTableSessionActiveAlert(error);
        return;
      }

      Alert.alert('QR inválido', getApiError(error) || 'No pudimos reconocer esta mesa. Intenta con otro QR.', [
        {
          text: 'Intentar de nuevo',
          onPress: resetScanLock,
        },
      ]);
    } finally {
      setIsProcessing(false);
    }
  }

  function handleResolvedTable(table: TableScanResult) {
    const applyTable = () => {
      if (tableAppliedRef.current) return;
      tableAppliedRef.current = true;

      setTableSession(table);
      seleccionar(table.branch);
      setTipoPedido('eat_in');
      void queryClient.invalidateQueries({ queryKey: ['menu', table.restaurante_id] });
      updateProfile({
        current_restaurante_id: table.restaurante_id,
        mesa: table.mesa_value,
      });
      setConfirmedTable(table);
      successTimerRef.current = setTimeout(continueAfterScan, 1800);
    };

    if (itemCount > 0 && cartRestaurantId !== null && cartRestaurantId !== table.restaurante_id) {
      Alert.alert(
        'Cambiar sucursal',
        'Tu carrito tiene platillos de otra sucursal. Para usar esta mesa necesitamos vaciarlo.',
        [
          {
            text: 'Cancelar',
            style: 'cancel',
            onPress: resetScanLock,
          },
          {
            text: 'Vaciar y continuar',
            style: 'destructive',
            onPress: () => {
              clear();
              applyTable();
            },
          },
        ]
      );
      return;
    }

    applyTable();
  }

  if (!permission) {
    return (
      <SafeAreaView style={styles.safe}>
        <View style={styles.center}>
          <ActivityIndicator color={Colors.primary || '#111827'} />
        </View>
      </SafeAreaView>
    );
  }

  if (confirmedTable) {
    return (
      <SafeAreaView style={styles.safe}>
        <View style={styles.successContainer}>
          <View style={styles.successIcon}>
            <Ionicons name="checkmark" size={42} color="#FFFFFF" />
          </View>

          <Text style={styles.successEyebrow}>Mesa guardada</Text>
          <Text style={styles.successTitle}>Estás en {confirmedTable.mesa_label}</Text>
          <Text style={styles.successSubtitle}>
            {confirmedBranchName} quedó asociado a tu visita para pedir comida, pagar y activar el modo social.
          </Text>

          <View style={styles.tableSummaryCard}>
            <View style={styles.tableSummaryIcon}>
              <Ionicons name="restaurant-outline" size={22} color={Colors.primary || '#111827'} />
            </View>
            <View style={styles.tableSummaryCopy}>
              <Text style={styles.tableSummaryLabel}>Ubicación actual</Text>
              <Text style={styles.tableSummaryValue} numberOfLines={1}>
                {confirmedTable.mesa_label} · {confirmedBranchName}
              </Text>
            </View>
          </View>

          <TouchableOpacity style={styles.primaryButtonWide} activeOpacity={0.86} onPress={continueAfterScan}>
            <Text style={styles.primaryButtonText}>Continuar</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  if (!permission.granted) {
    return (
      <SafeAreaView style={styles.safe}>
        <View style={styles.header}>
          <TouchableOpacity onPress={() => router.back()} style={styles.iconButton}>
            <Ionicons name="close" size={22} color="#111827" />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Escanear mesa</Text>
          <View style={{ width: 40 }} />
        </View>

        <View style={styles.center}>
          <View style={styles.permissionIcon}>
            <Ionicons name="camera-outline" size={42} color={Colors.primary || '#111827'} />
          </View>
          <Text style={styles.title}>Permiso de cámara</Text>
          <Text style={styles.subtitle}>
            Necesitamos la cámara para leer el QR de tu mesa y habilitar Comer aquí.
          </Text>
          <TouchableOpacity style={styles.primaryButton} onPress={handleCameraPermissionRequest}>
            <Text style={styles.primaryButtonText}>Permitir cámara</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.secondaryButton} onPress={handleScanLater}>
            <Ionicons name="time-outline" size={17} color={Colors.primary || '#111827'} />
            <Text style={styles.secondaryButtonText}>Escanear más tarde</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeDark}>
      <View style={styles.headerDark}>
        <TouchableOpacity onPress={() => router.back()} style={styles.iconButtonDark}>
          <Ionicons name="close" size={22} color="#FFFFFF" />
        </TouchableOpacity>
        <Text style={styles.headerTitleDark}>Escanear mesa</Text>
        <View style={{ width: 40 }} />
      </View>

      <View style={styles.cameraWrap}>
        <CameraView
          style={styles.camera}
          facing="back"
          barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
          onBarcodeScanned={scanLocked ? undefined : handleBarcodeScanned}
        />
        <View style={styles.scanOverlay} pointerEvents="none">
          <View style={styles.scanFrame} />
        </View>
      </View>

      <View style={styles.bottomPanel}>
        <Text style={styles.title}>Escanea el QR de tu mesa</Text>
        <Text style={styles.subtitle}>
          Al leerlo guardaremos tu mesa para pedidos y perfil social.
        </Text>
        {isProcessing ? (
          <View style={styles.processingRow}>
            <ActivityIndicator size="small" color={Colors.primary || '#111827'} />
            <Text style={styles.processingText}>Validando mesa...</Text>
          </View>
        ) : null}
        <TouchableOpacity style={styles.secondaryButton} onPress={handleScanLater} disabled={isProcessing}>
          <Ionicons name="time-outline" size={17} color={Colors.primary || '#111827'} />
          <Text style={styles.secondaryButtonText}>Escanear más tarde</Text>
        </TouchableOpacity>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: '#FFFFFF',
  },
  safeDark: {
    flex: 1,
    backgroundColor: '#111827',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.base || 16,
    paddingVertical: 12,
  },
  headerDark: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.base || 16,
    paddingVertical: 12,
  },
  iconButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#F3F4F6',
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconButtonDark: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(255,255,255,0.16)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '800',
    color: '#111827',
  },
  headerTitleDark: {
    fontSize: 18,
    fontWeight: '800',
    color: '#FFFFFF',
  },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    gap: 16,
  },
  permissionIcon: {
    width: 82,
    height: 82,
    borderRadius: 41,
    backgroundColor: '#F9FAFB',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  successContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    gap: 14,
  },
  successIcon: {
    width: 86,
    height: 86,
    borderRadius: 43,
    backgroundColor: Colors.primary || '#111827',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 6,
  },
  successEyebrow: {
    fontSize: 12,
    color: '#6B7280',
    fontWeight: '900',
    textTransform: 'uppercase',
    letterSpacing: 1.1,
  },
  successTitle: {
    fontSize: 27,
    fontWeight: '900',
    color: '#111827',
    textAlign: 'center',
  },
  successSubtitle: {
    maxWidth: 320,
    fontSize: 15,
    color: '#6B7280',
    textAlign: 'center',
    lineHeight: 22,
  },
  tableSummaryCard: {
    width: '100%',
    maxWidth: 340,
    minHeight: 70,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#F9FAFB',
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    gap: 12,
    marginTop: 8,
  },
  tableSummaryIcon: {
    width: 44,
    height: 44,
    borderRadius: 16,
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
  },
  tableSummaryCopy: {
    flex: 1,
    minWidth: 0,
  },
  tableSummaryLabel: {
    fontSize: 11,
    color: '#6B7280',
    fontWeight: '800',
    textTransform: 'uppercase',
  },
  tableSummaryValue: {
    marginTop: 2,
    fontSize: 15,
    color: '#111827',
    fontWeight: '900',
  },
  title: {
    fontSize: 22,
    fontWeight: '900',
    color: '#111827',
    textAlign: 'center',
  },
  subtitle: {
    fontSize: 15,
    color: '#6B7280',
    textAlign: 'center',
    lineHeight: 21,
  },
  primaryButton: {
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderRadius: 14,
    backgroundColor: Colors.primary || '#111827',
  },
  primaryButtonWide: {
    width: '100%',
    maxWidth: 340,
    minHeight: 52,
    borderRadius: 16,
    backgroundColor: Colors.primary || '#111827',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 8,
  },
  primaryButtonText: {
    color: '#FFFFFF',
    fontWeight: '800',
  },
  secondaryButton: {
    minHeight: 46,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#D8DDE8',
    backgroundColor: '#FFFFFF',
    paddingHorizontal: 18,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
    marginTop: 8,
  },
  secondaryButtonText: {
    color: Colors.primary || '#111827',
    fontWeight: '800',
    fontSize: 14,
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
    alignItems: 'center',
    justifyContent: 'center',
  },
  scanFrame: {
    width: 250,
    height: 250,
    borderRadius: 24,
    borderWidth: 3,
    borderColor: '#FFFFFF',
    backgroundColor: 'transparent',
  },
  bottomPanel: {
    backgroundColor: '#FFFFFF',
    paddingHorizontal: 24,
    paddingTop: 20,
    paddingBottom: 34,
    gap: 8,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
  },
  processingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    marginTop: 8,
  },
  processingText: {
    fontSize: 13,
    color: '#6B7280',
    fontWeight: '700',
  },
});
