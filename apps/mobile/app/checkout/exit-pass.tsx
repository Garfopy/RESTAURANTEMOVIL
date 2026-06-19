import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { ExitQrCode } from '../../components/shared/ExitQrCode';
import { getExitPass } from '../../services/orders.service';
import { Colors, Spacing } from '../../theme';

export default function ExitPassScreen() {
  const router = useRouter();
  const { orderId, payload, folio, mesaLabel } = useLocalSearchParams<{
    orderId: string;
    payload?: string;
    folio?: string;
    mesaLabel?: string;
  }>();

  const numericOrderId = Number(orderId);
  const [qrPayload, setQrPayload] = useState(payload || '');
  const [isValidated, setIsValidated] = useState(false);
  const [checking, setChecking] = useState(false);
  const [pollingDisabled, setPollingDisabled] = useState(false);
  const [passUnavailable, setPassUnavailable] = useState(false);
  const handledValidationRef = useRef(false);
  const missingPassCountRef = useRef(0);

  const safeFolio = useMemo(() => (typeof folio === 'string' && folio ? folio : `Pedido #${orderId}`), [folio, orderId]);
  const safeMesa = typeof mesaLabel === 'string' && mesaLabel ? mesaLabel : 'Mesa asignada';

  useEffect(() => {
    if (!numericOrderId || Number.isNaN(numericOrderId) || pollingDisabled) {
      return;
    }

    let mounted = true;
    async function checkStatus(showLoading = false) {
      try {
        if (showLoading) setChecking(true);
        const exitPass = await getExitPass(numericOrderId, { suppressConsoleError: true });
        if (!mounted) return;

        missingPassCountRef.current = 0;
        setPassUnavailable(false);
        setQrPayload(exitPass.payload);
        setIsValidated(Boolean(exitPass.is_validated));

        if (exitPass.is_validated && !handledValidationRef.current) {
          handledValidationRef.current = true;
          Alert.alert('Salida validada', 'Tu cuenta quedo cerrada y la mesa fue liberada.', [
            { text: 'Listo', onPress: () => router.replace('/(tabs)' as never) },
          ]);
        }
      } catch (error: any) {
        if (error?.response?.status === 404) {
          missingPassCountRef.current += 1;
          if (missingPassCountRef.current >= 3) {
            setPollingDisabled(true);
            if (!qrPayload) {
              setPassUnavailable(true);
            }
          }
          return;
        }
        console.error('Error consultando pase de salida:', error);
      } finally {
        if (mounted) setChecking(false);
      }
    }

    checkStatus(!qrPayload);
    const interval = setInterval(() => {
      checkStatus(false);
    }, 4000);

    return () => {
      mounted = false;
      clearInterval(interval);
    };
  }, [numericOrderId, pollingDisabled, qrPayload, router]);

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => router.replace('/(tabs)' as never)} accessibilityRole="button">
          <Ionicons name="close" size={24} color={Colors.text || '#111827'} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>QR de salida</Text>
        <View style={{ width: 24 }} />
      </View>

      <View style={styles.content}>
        <View style={styles.statusIcon}>
          <Ionicons name={isValidated ? 'checkmark-circle' : 'qr-code-outline'} size={42} color={Colors.primary || '#111827'} />
        </View>

        <Text style={styles.title}>{isValidated ? 'Salida validada' : 'Muestra este QR al salir'}</Text>
        <Text style={styles.subtitle}>
          {passUnavailable
            ? 'Estamos generando tu pase de salida. Si ya pagaste, vuelve a abrir esta pantalla en unos segundos.'
            : isValidated
            ? 'Hostess cerro tu visita y libero la mesa.'
            : 'Hostess escaneara este pase para cerrar tu pantalla y liberar la mesa.'}
        </Text>

        <View style={styles.qrFrame}>
          {qrPayload ? <ExitQrCode value={qrPayload} size={250} /> : <ActivityIndicator color={Colors.primary || '#111827'} />}
        </View>

        <View style={styles.details}>
          <View>
            <Text style={styles.detailLabel}>Pedido</Text>
            <Text style={styles.detailValue}>{safeFolio}</Text>
          </View>
          <View>
            <Text style={styles.detailLabel}>Mesa</Text>
            <Text style={styles.detailValue}>{safeMesa}</Text>
          </View>
        </View>

        <TouchableOpacity
          style={styles.refreshButton}
          onPress={() => {
            if (!numericOrderId || Number.isNaN(numericOrderId)) return;
            setChecking(true);
            setPollingDisabled(false);
            missingPassCountRef.current = 0;
            getExitPass(numericOrderId, { suppressConsoleError: true })
              .then((exitPass) => {
                setPassUnavailable(false);
                setQrPayload(exitPass.payload);
                setIsValidated(Boolean(exitPass.is_validated));
              })
              .catch((error: any) => {
                if (error?.response?.status === 404) {
                  setPassUnavailable(!qrPayload);
                  return;
                }
                console.error('Error refrescando pase:', error);
              })
              .finally(() => setChecking(false));
          }}
          disabled={checking}
        >
          {checking ? (
            <ActivityIndicator size="small" color={Colors.primary || '#111827'} />
          ) : (
            <Ionicons name="refresh" size={18} color={Colors.primary || '#111827'} />
          )}
          <Text style={styles.refreshText}>Actualizar estado</Text>
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
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.base || 16,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '800',
    color: '#111827',
  },
  content: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    gap: 16,
  },
  statusIcon: {
    width: 76,
    height: 76,
    borderRadius: 38,
    backgroundColor: '#F9FAFB',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  title: {
    fontSize: 24,
    fontWeight: '900',
    color: '#111827',
    textAlign: 'center',
  },
  subtitle: {
    fontSize: 15,
    color: '#6B7280',
    textAlign: 'center',
    lineHeight: 21,
    maxWidth: 320,
  },
  qrFrame: {
    width: 282,
    height: 282,
    borderRadius: 18,
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  details: {
    width: '100%',
    maxWidth: 320,
    padding: 16,
    borderRadius: 14,
    backgroundColor: '#F9FAFB',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    gap: 12,
  },
  detailLabel: {
    fontSize: 12,
    color: '#6B7280',
    fontWeight: '700',
    textTransform: 'uppercase',
  },
  detailValue: {
    fontSize: 15,
    color: '#111827',
    fontWeight: '800',
    marginTop: 2,
  },
  refreshButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  refreshText: {
    color: Colors.primary || '#111827',
    fontWeight: '800',
  },
});
