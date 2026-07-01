import React, { useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { CameraView, useCameraPermissions, type BarcodeScanningResult } from 'expo-camera';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import type { ExitPass } from '@amare/types';
import { scanExitPass } from '../../services/orders.service';
import { getApiError } from '../../services/api';
import { useUserStore } from '../../store/user.store';
import { Colors, Shadows } from '../../theme';

export default function HostessDashboardScreen() {
  const [permission, requestPermission] = useCameraPermissions();
  const [scannerVisible, setScannerVisible] = useState(false);
  const [scanLocked, setScanLocked] = useState(false);
  const [isScanning, setIsScanning] = useState(false);
  const [lastPass, setLastPass] = useState<ExitPass | null>(null);
  const [lastValidationAt, setLastValidationAt] = useState<string | null>(null);
  const [validatedCount, setValidatedCount] = useState(0);
  const [refreshing, setRefreshing] = useState(false);
  const scanLockedRef = useRef(false);
  const logout = useUserStore((state) => state.logout);
  const user = useUserStore((state) => state.user);

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
    return new Intl.DateTimeFormat('es-MX', {
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(lastValidationAt));
  }, [lastValidationAt]);

  const cameraStatus = permission?.granted ? 'Cámara lista' : 'Permiso pendiente';
  const cameraStatusColor = permission?.granted ? Colors.success : Colors.warning;

  function unlockScanner() {
    scanLockedRef.current = false;
    setScanLocked(false);
    setIsScanning(false);
  }

  async function onRefresh() {
    setRefreshing(true);
    setTimeout(() => setRefreshing(false), 350);
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
      Alert.alert('QR inválido', getApiError(error) || 'No pudimos validar este pase de salida.', [
        { text: 'Intentar de nuevo', onPress: unlockScanner },
      ]);
    } finally {
      setIsScanning(false);
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
            <Text style={styles.scannerKicker}>Validación de salida</Text>
            <Text style={styles.scannerTitle}>Escáner QR</Text>
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
        contentContainerStyle={styles.content}
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
            <View>
              <Text style={styles.eyebrow}>Hostess</Text>
              <Text style={styles.title}>Recepción</Text>
            </View>
            <View style={styles.datePill}>
              <Ionicons name="calendar-outline" size={15} color="#F9FAFB" />
              <Text style={styles.datePillText}>{todayLabel}</Text>
            </View>
          </View>
        </LinearGradient>

        <View style={styles.metricsGrid}>
          <MetricCard icon="scan-outline" label="Estado" value={cameraStatus} accentColor={cameraStatusColor} />
          <MetricCard icon="checkmark-done-outline" label="Validaciones" value={String(validatedCount)} accentColor={Colors.success} />
          <MetricCard icon="time-outline" label="Última" value={lastValidationTime} accentColor={Colors.info} />
        </View>

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

        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Pedidos para liberar</Text>
            <Ionicons name="bag-check-outline" size={19} color={Colors.primary} />
          </View>
          <ReleaseQueueRow
            icon="bag-handle-outline"
            title="Pickup"
            subtitle="Pedidos listos para entregar en mostrador."
            count={0}
          />
          <ReleaseQueueRow
            icon="bicycle-outline"
            title="Delivery"
            subtitle="Pedidos listos para salida a domicilio."
            count={0}
          />
        </View>

        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Última salida</Text>
            <View style={styles.sectionIcon}>
              <Ionicons name={lastPass ? 'checkmark-circle' : 'remove-circle-outline'} size={18} color={lastPass ? Colors.success : Colors.textMuted} />
            </View>
          </View>

          {lastPass ? (
            <View style={styles.lastPassCard}>
              <View style={styles.lastPassIcon}>
                <Ionicons name="receipt-outline" size={22} color={Colors.primary} />
              </View>
              <View style={styles.lastPassCopy}>
                <Text style={styles.lastPassTitle}>{lastPass.folio ?? `Pedido ${lastPass.pedido_id}`}</Text>
                <Text style={styles.lastPassMeta}>
                  {lastPass.mesa_id ? `Mesa ${lastPass.mesa_id}` : 'Mesa validada'} · {lastValidationTime}
                </Text>
              </View>
              <View style={styles.validatedBadge}>
                <Text style={styles.validatedBadgeText}>Liberada</Text>
              </View>
            </View>
          ) : (
            <View style={styles.emptyState}>
              <Ionicons name="sparkles-outline" size={24} color={Colors.textMuted} />
              <Text style={styles.emptyTitle}>Sin salidas validadas</Text>
              <Text style={styles.emptyText}>El último pase escaneado aparecerá aquí.</Text>
            </View>
          )}
        </View>

        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Control de puerta</Text>
            <Ionicons name="shield-checkmark-outline" size={19} color={Colors.primary} />
          </View>
          <ControlRow icon="wallet-outline" title="Cuenta liquidada" state="Requerida" />
          <ControlRow icon="qr-code-outline" title="QR de salida" state="Activo" />
          <ControlRow icon="restaurant-outline" title="Mesa" state="Se libera al validar" />
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

function MetricCard({
  icon,
  label,
  value,
  accentColor,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  value: string;
  accentColor: string;
}) {
  return (
    <View style={styles.metricCard}>
      <View style={[styles.metricIcon, { backgroundColor: `${accentColor}18` }]}>
        <Ionicons name={icon} size={18} color={accentColor} />
      </View>
      <Text style={styles.metricLabel}>{label}</Text>
      <Text style={styles.metricValue} numberOfLines={1} adjustsFontSizeToFit>
        {value}
      </Text>
    </View>
  );
}

function ControlRow({
  icon,
  title,
  state,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  title: string;
  state: string;
}) {
  return (
    <View style={styles.controlRow}>
      <View style={styles.controlIcon}>
        <Ionicons name={icon} size={20} color={Colors.primary} />
      </View>
      <Text style={styles.controlTitle}>{title}</Text>
      <Text style={styles.controlState}>{state}</Text>
    </View>
  );
}

function ReleaseQueueRow({
  icon,
  title,
  subtitle,
  count,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  title: string;
  subtitle: string;
  count: number;
}) {
  return (
    <TouchableOpacity style={styles.releaseRow} activeOpacity={0.82}>
      <View style={styles.releaseIcon}>
        <Ionicons name={icon} size={21} color={Colors.primary} />
      </View>
      <View style={styles.releaseCopy}>
        <Text style={styles.releaseTitle}>{title}</Text>
        <Text style={styles.releaseSubtitle}>{subtitle}</Text>
      </View>
      <View style={[styles.releaseCount, count > 0 && styles.releaseCountActive]}>
        <Text style={[styles.releaseCountText, count > 0 && styles.releaseCountTextActive]}>{count}</Text>
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: '#F4F5F7',
  },
  content: {
    padding: 16,
    gap: 14,
    paddingBottom: 34,
  },
  headerPanel: {
    borderRadius: 8,
    padding: 18,
    gap: 22,
    ...Shadows.md,
  },
  headerTop: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  operatorBadge: {
    minHeight: 34,
    borderRadius: 8,
    paddingHorizontal: 11,
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
    width: 38,
    height: 38,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.13)',
  },
  headerMain: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    justifyContent: 'space-between',
    gap: 14,
  },
  eyebrow: {
    color: Colors.accentLight || '#F5C060',
    fontSize: 12,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  title: {
    color: '#FFFFFF',
    fontSize: 34,
    fontWeight: '900',
    marginTop: 2,
  },
  datePill: {
    minHeight: 34,
    borderRadius: 8,
    paddingHorizontal: 10,
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
  metricsGrid: {
    flexDirection: 'row',
    gap: 10,
  },
  metricCard: {
    flex: 1,
    minHeight: 104,
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E7E9EF',
    padding: 12,
    justifyContent: 'space-between',
    ...Shadows.sm,
  },
  metricIcon: {
    width: 30,
    height: 30,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  metricLabel: {
    color: '#6B7280',
    fontSize: 11,
    fontWeight: '800',
    textTransform: 'uppercase',
  },
  metricValue: {
    color: Colors.text,
    fontSize: 17,
    fontWeight: '900',
  },
  primaryAction: {
    minHeight: 96,
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E7E9EF',
    padding: 16,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    ...Shadows.card,
  },
  primaryIcon: {
    width: 56,
    height: 56,
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
    fontSize: 22,
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
    backgroundColor: '#F3F4F6',
  },
  section: {
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E7E9EF',
    padding: 14,
    gap: 12,
    ...Shadows.sm,
  },
  sectionHeader: {
    minHeight: 28,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  sectionTitle: {
    color: Colors.text,
    fontSize: 16,
    fontWeight: '900',
  },
  sectionIcon: {
    width: 28,
    height: 28,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F8FAFC',
  },
  releaseRow: {
    minHeight: 62,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingTop: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: '#E5E7EB',
  },
  releaseIcon: {
    width: 42,
    height: 42,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F4F5F7',
  },
  releaseCopy: {
    flex: 1,
  },
  releaseTitle: {
    color: Colors.text,
    fontSize: 15,
    fontWeight: '900',
  },
  releaseSubtitle: {
    color: Colors.textSecondary,
    fontSize: 12,
    fontWeight: '700',
    marginTop: 2,
  },
  releaseCount: {
    minWidth: 32,
    height: 32,
    borderRadius: 8,
    paddingHorizontal: 9,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#EEF0F4',
  },
  releaseCountActive: {
    backgroundColor: Colors.primary,
  },
  releaseCountText: {
    color: Colors.textSecondary,
    fontSize: 13,
    fontWeight: '900',
  },
  releaseCountTextActive: {
    color: '#FFFFFF',
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
  controlRow: {
    minHeight: 48,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: '#E5E7EB',
  },
  controlIcon: {
    width: 34,
    height: 34,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F4F5F7',
  },
  controlTitle: {
    flex: 1,
    color: Colors.text,
    fontSize: 14,
    fontWeight: '800',
  },
  controlState: {
    color: Colors.textSecondary,
    fontSize: 12,
    fontWeight: '800',
    textAlign: 'right',
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
