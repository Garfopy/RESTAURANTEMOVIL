import React, { useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  SafeAreaView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { CameraView, useCameraPermissions, type BarcodeScanningResult } from 'expo-camera';
import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import type { TableScanResult } from '@amare/types';
import { scanTableQr } from '../services/table-session.service';
import { getApiError } from '../services/api';
import { useBranchStore } from '../store/branch.store';
import { useCartStore } from '../store/cart.store';
import { useTableSessionStore } from '../store/table-session.store';
import { useUserStore } from '../store/user.store';
import { Colors, Spacing } from '../theme';

export default function TableScannerScreen() {
  const router = useRouter();
  const { returnTo, activateSocial } = useLocalSearchParams<{ returnTo?: string; activateSocial?: string }>();
  const [permission, requestPermission] = useCameraPermissions();
  const [isProcessing, setIsProcessing] = useState(false);
  const [scanLocked, setScanLocked] = useState(false);
  const scanLockedRef = useRef(false);
  const hasNavigatedRef = useRef(false);

  const seleccionar = useBranchStore((s) => s.seleccionar);
  const { itemCount, restauranteId: cartRestaurantId, clear, setTipoPedido } = useCartStore();
  const setTableSession = useTableSessionStore((s) => s.setSession);
  const updateProfile = useUserStore((s) => s.updateProfile);

  const destination = typeof returnTo === 'string' && returnTo.trim() ? returnTo : '/(tabs)';

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
      Alert.alert('QR invalido', getApiError(error) || 'No pudimos reconocer esta mesa. Intenta con otro QR.', [
        {
          text: 'Intentar de nuevo',
          onPress: () => {
            scanLockedRef.current = false;
            setScanLocked(false);
          },
        },
      ]);
    } finally {
      setIsProcessing(false);
    }
  }

  function handleResolvedTable(table: TableScanResult) {
    const applyTable = () => {
      if (hasNavigatedRef.current) return;
      hasNavigatedRef.current = true;

      setTableSession(table);
      seleccionar(table.branch);
      setTipoPedido('eat_in');
      updateProfile({
        current_restaurante_id: table.restaurante_id,
        mesa: table.mesa_value,
      });
      if (activateSocial === '1' && destination === '/profile/social') {
        router.replace({ pathname: '/profile/social', params: { activateSocial: '1' } } as never);
        return;
      }
      router.replace(destination as never);
    };

    if (itemCount > 0 && cartRestaurantId !== null && cartRestaurantId !== table.restaurante_id) {
      Alert.alert(
        'Cambiar sucursal',
        'Tu carrito tiene platillos de otra sucursal. Para usar esta mesa necesitamos vaciarlo.',
        [
          {
            text: 'Cancelar',
            style: 'cancel',
            onPress: () => {
              scanLockedRef.current = false;
              setScanLocked(false);
            },
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
          <Text style={styles.title}>Permiso de camara</Text>
          <Text style={styles.subtitle}>
            Necesitamos la camara para leer el QR de tu mesa y habilitar Comer aqui.
          </Text>
          <TouchableOpacity style={styles.primaryButton} onPress={requestPermission}>
            <Text style={styles.primaryButtonText}>Permitir camara</Text>
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
  primaryButtonText: {
    color: '#FFFFFF',
    fontWeight: '800',
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
