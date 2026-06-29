import React, { useRef, useState } from 'react';
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
import type { ExitPass } from '@amare/types';
import { scanExitPass } from '../../services/orders.service';
import { getApiError } from '../../services/api';
import { useUserStore } from '../../store/user.store';
import { Colors } from '../../theme';

export default function HostessDashboardScreen() {
  const [permission, requestPermission] = useCameraPermissions();
  const [scannerVisible, setScannerVisible] = useState(false);
  const [scanLocked, setScanLocked] = useState(false);
  const [isScanning, setIsScanning] = useState(false);
  const [lastPass, setLastPass] = useState<ExitPass | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const scanLockedRef = useRef(false);
  const logout = useUserStore((state) => state.logout);

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
        Alert.alert('Permiso requerido', 'Necesitamos la camara para validar QRs de salida.');
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

  if (scannerVisible) {
    return (
      <SafeAreaView style={styles.scannerSafe}>
        <View style={styles.scannerHeader}>
          <TouchableOpacity onPress={() => setScannerVisible(false)} style={styles.darkIconButton}>
            <Ionicons name="close" size={22} color="#FFFFFF" />
          </TouchableOpacity>
          <Text style={styles.scannerTitle}>QR de salida</Text>
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

        <View style={styles.scannerFooter}>
          <Text style={styles.scannerFooterTitle}>Escanea el pase del comensal</Text>
          <Text style={styles.scannerFooterText}>Al validarlo se marca la salida y se libera la visita.</Text>
          {isScanning ? (
            <View style={styles.processingRow}>
              <ActivityIndicator color={Colors.primary || '#B91C1C'} />
              <Text style={styles.processingText}>Validando...</Text>
            </View>
          ) : null}
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <View>
          <Text style={styles.eyebrow}>Hostess</Text>
          <Text style={styles.title}>Recepcion</Text>
        </View>
        <TouchableOpacity onPress={() => void logout()} style={styles.iconButton}>
          <Ionicons name="log-out-outline" size={22} color="#111827" />
        </TouchableOpacity>
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void onRefresh()} />}
        showsVerticalScrollIndicator={false}
      >
        <TouchableOpacity style={styles.primaryAction} onPress={handleStartScanner} activeOpacity={0.88}>
          <Ionicons name="qr-code-outline" size={24} color="#FFFFFF" />
          <View style={styles.primaryActionText}>
            <Text style={styles.primaryActionTitle}>Escanear salida</Text>
            <Text style={styles.primaryActionSubtitle}>Valida el QR generado al pagar.</Text>
          </View>
          <Ionicons name="chevron-forward" size={22} color="#FFFFFF" />
        </TouchableOpacity>

        {lastPass ? (
          <View style={styles.statusPanel}>
            <View style={styles.statusIcon}>
              <Ionicons name="checkmark-circle-outline" size={22} color="#047857" />
            </View>
            <View style={styles.statusCopy}>
              <Text style={styles.statusTitle}>Ultima salida validada</Text>
              <Text style={styles.statusText}>
                {lastPass.folio ?? `Pedido ${lastPass.pedido_id}`} {lastPass.mesa_id ? `- Mesa ${lastPass.mesa_id}` : ''}
              </Text>
            </View>
          </View>
        ) : null}

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Pedidos para liberar</Text>
          <View style={styles.queueRow}>
            <Ionicons name="bag-handle-outline" size={22} color="#B91C1C" />
            <View style={styles.queueCopy}>
              <Text style={styles.queueTitle}>Pickup</Text>
              <Text style={styles.queueText}>Sin pedidos pendientes en este dispositivo.</Text>
            </View>
          </View>
          <View style={styles.queueRow}>
            <Ionicons name="bicycle-outline" size={22} color="#B91C1C" />
            <View style={styles.queueCopy}>
              <Text style={styles.queueTitle}>Delivery</Text>
              <Text style={styles.queueText}>Sin pedidos pendientes en este dispositivo.</Text>
            </View>
          </View>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Reservaciones</Text>
          <View style={styles.queueRow}>
            <Ionicons name="calendar-outline" size={22} color="#B91C1C" />
            <View style={styles.queueCopy}>
              <Text style={styles.queueTitle}>Llegadas de hoy</Text>
              <Text style={styles.queueText}>Sin reservaciones cargadas para esta sesion.</Text>
            </View>
          </View>
        </View>
      </ScrollView>
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
    paddingTop: 16,
    paddingBottom: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: '#FFFFFF',
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#E5E7EB',
  },
  eyebrow: {
    color: '#B91C1C',
    fontSize: 12,
    fontWeight: '800',
    textTransform: 'uppercase',
  },
  title: {
    color: '#111827',
    fontSize: 28,
    fontWeight: '900',
  },
  iconButton: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F3F4F6',
  },
  content: {
    padding: 20,
    gap: 16,
    paddingBottom: 42,
  },
  primaryAction: {
    minHeight: 88,
    borderRadius: 8,
    backgroundColor: '#B91C1C',
    padding: 18,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
  },
  primaryActionText: {
    flex: 1,
  },
  primaryActionTitle: {
    color: '#FFFFFF',
    fontSize: 18,
    fontWeight: '900',
  },
  primaryActionSubtitle: {
    color: '#FEE2E2',
    fontSize: 13,
    marginTop: 3,
  },
  statusPanel: {
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#D1FAE5',
    padding: 14,
    flexDirection: 'row',
    gap: 12,
    alignItems: 'center',
  },
  statusIcon: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#ECFDF5',
  },
  statusCopy: {
    flex: 1,
  },
  statusTitle: {
    color: '#065F46',
    fontSize: 14,
    fontWeight: '800',
  },
  statusText: {
    color: '#374151',
    fontSize: 13,
    marginTop: 2,
  },
  section: {
    backgroundColor: '#FFFFFF',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 16,
    gap: 12,
  },
  sectionTitle: {
    color: '#111827',
    fontSize: 16,
    fontWeight: '900',
  },
  queueRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: '#E5E7EB',
  },
  queueCopy: {
    flex: 1,
  },
  queueTitle: {
    color: '#111827',
    fontSize: 14,
    fontWeight: '800',
  },
  queueText: {
    color: '#6B7280',
    fontSize: 13,
    marginTop: 2,
  },
  scannerSafe: {
    flex: 1,
    backgroundColor: '#111827',
  },
  scannerHeader: {
    paddingHorizontal: 16,
    paddingVertical: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  darkIconButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.14)',
  },
  scannerTitle: {
    color: '#FFFFFF',
    fontSize: 17,
    fontWeight: '900',
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
    width: 240,
    height: 240,
    borderRadius: 8,
    borderWidth: 3,
    borderColor: '#FFFFFF',
    backgroundColor: 'rgba(255,255,255,0.04)',
  },
  scannerFooter: {
    backgroundColor: '#FFFFFF',
    padding: 20,
    gap: 8,
  },
  scannerFooterTitle: {
    color: '#111827',
    fontSize: 20,
    fontWeight: '900',
  },
  scannerFooterText: {
    color: '#6B7280',
    fontSize: 14,
  },
  processingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginTop: 8,
  },
  processingText: {
    color: '#374151',
    fontSize: 13,
    fontWeight: '700',
  },
});
