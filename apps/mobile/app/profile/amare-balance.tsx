import React, { useRef, useState } from 'react';
import {
  Alert,
  Keyboard,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useFocusEffect, useRouter } from 'expo-router';
import { useStripe } from '@stripe/stripe-react-native';
import { Ionicons } from '@expo/vector-icons';
import { Button } from '../../components/ui/Button';
import { STRIPE_IS_CONFIGURED } from '../../constants/stripe';
import {
  confirmRewardsTopup,
  createRewardsTopupIntent,
  getRewardsWallet,
  type RewardsTopupOption,
  type RewardsWallet,
} from '../../services/rewards.service';
import { Colors, Shadows } from '../../theme';
import { presentAmarePaymentSheet, stripePaymentLabel } from '../../services/stripe-payment-sheet.service';

const FALLBACK_TOPUPS: RewardsTopupOption[] = [
  { amount_mxn: 500 },
  { amount_mxn: 1000 },
  { amount_mxn: 3000 },
];

export default function AmareBalanceScreen() {
  const router = useRouter();
  const stripe = useStripe();
  const [wallet, setWallet] = useState<RewardsWallet | null>(null);
  const [loading, setLoading] = useState(false);
  const [selectedAmount, setSelectedAmount] = useState(500);
  const topupRequestKeyRef = useRef<string | null>(null);

  useFocusEffect(
    React.useCallback(() => {
      void loadWallet();
      return () => {
        Keyboard.dismiss();
      };
    }, [])
  );

  async function loadWallet() {
    try {
      setWallet(await getRewardsWallet());
    } catch (error) {
      console.warn('No se pudo cargar Saldo Amare', error);
    }
  }

  const topupOptions = wallet?.topup_options?.length ? wallet.topup_options : FALLBACK_TOPUPS;

  async function handleTopup() {
    if (!STRIPE_IS_CONFIGURED) {
      Alert.alert('Stripe no disponible', 'La app no tiene Stripe configurado para recargar saldo.');
      return;
    }
    if (!selectedAmount || selectedAmount <= 0) {
      Alert.alert('Monto requerido', 'Ingresa un monto válido para recargar tu prepago.');
      return;
    }
    setLoading(true);
    let paymentPresented = false;
    try {
      topupRequestKeyRef.current ??= `topup_${Date.now()}_${Math.random().toString(36).slice(2, 14)}`;
      const prepared = await createRewardsTopupIntent(selectedAmount, topupRequestKeyRef.current);
      await presentAmarePaymentSheet(stripe, {
        clientSecret: prepared.client_secret,
      });
      paymentPresented = true;

      const nextWallet = await confirmRewardsTopup(prepared.payment_intent_id);
      topupRequestKeyRef.current = null;
      setWallet(nextWallet);
      Alert.alert('Recarga exitosa', `Tu Saldo Amare ahora es de $${nextWallet.balance_mxn.toFixed(2)}.`);
    } catch (error: any) {
      if (error?.name === 'PaymentCanceledError') return;
      if (paymentPresented) {
        Alert.alert(
          'Recarga en proceso',
          'Stripe recibio el pago y Amare esta conciliando tu saldo. No intentes otra recarga; vuelve a esta pantalla en unos momentos.'
        );
        await loadWallet();
        return;
      }
      Alert.alert('No se pudo recargar', error?.message || 'Intenta de nuevo en unos momentos.');
    } finally {
      setLoading(false);
    }
  }

  function handleBack() {
    Keyboard.dismiss();
    router.back();
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity onPress={handleBack} accessibilityRole="button">
          <Ionicons name="arrow-back" size={24} color={Colors.text} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Saldo Amare</Text>
        <View style={{ width: 24 }} />
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="interactive"
      >
        <View style={styles.heroCard}>
          <Text style={styles.heroLabel}>Disponible</Text>
          <Text style={styles.heroValue}>${Number(wallet?.balance_mxn ?? 0).toFixed(2)}</Text>
          <Text style={styles.heroHint}>Paga con tu prepago y recibe 10% de descuento directo.</Text>
        </View>

        <View style={styles.sectionCard}>
          <Text style={styles.sectionTitle}>Recargar prepago</Text>
          <Text style={styles.sectionHint}>Elige uno de los montos disponibles para agregar saldo.</Text>

          <View style={styles.amountRow}>
            {topupOptions.map((option) => {
              const active = selectedAmount === option.amount_mxn;
              return (
                <TouchableOpacity
                  key={option.amount_mxn}
                  style={[styles.amountChip, active && styles.amountChipActive]}
                  onPress={() => {
                    topupRequestKeyRef.current = null;
                    setSelectedAmount(option.amount_mxn);
                  }}
                  activeOpacity={0.88}
                >
                  <Text style={[styles.amountChipText, active && styles.amountChipTextActive]}>${option.amount_mxn}</Text>
                </TouchableOpacity>
              );
            })}

          </View>
          {STRIPE_IS_CONFIGURED ? (
            <>
              <Text style={styles.helperText}>
                El saldo comprado solo puede usarse en productos físicos de Amare y puede solicitarse su reembolso a soporte.
              </Text>
              <Button
                label={selectedAmount > 0
                  ? `Recargar $${selectedAmount} con ${stripePaymentLabel()}`
                  : 'Selecciona un monto para recargar'}
                onPress={handleTopup}
                fullWidth
                loading={loading}
                disabled={loading || selectedAmount <= 0}
              />
            </>
          ) : (
            <Text style={styles.helperText}>Stripe no está configurado en esta app. Agrega la llave pública para habilitar recargas.</Text>
          )}
        </View>

      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background || '#F9FAFB' },
  flex: { flex: 1 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingTop: 14,
    paddingBottom: 10,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: Colors.text || '#111827',
  },
  content: {
    paddingHorizontal: 20,
    paddingBottom: 40,
    gap: 16,
  },
  heroCard: {
    backgroundColor: '#ECFDF5',
    borderRadius: 24,
    borderWidth: 1,
    borderColor: '#BBF7D0',
    padding: 18,
    ...Shadows.sm,
  },
  heroLabel: {
    fontSize: 13,
    fontWeight: '700',
    color: '#047857',
  },
  heroValue: {
    marginTop: 8,
    fontSize: 30,
    fontWeight: '900',
    color: '#064E3B',
  },
  heroHint: {
    marginTop: 6,
    fontSize: 12,
    lineHeight: 18,
    color: '#047857',
    fontWeight: '600',
  },
  sectionCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 24,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 16,
    gap: 14,
    ...Shadows.sm,
  },
  sectionTitle: {
    fontSize: 17,
    fontWeight: '900',
    color: '#111827',
  },
  sectionHint: {
    marginTop: -6,
    fontSize: 12,
    lineHeight: 18,
    color: '#6B7280',
    fontWeight: '600',
  },
  amountRow: {
    flexDirection: 'row',
    alignItems: 'stretch',
    gap: 8,
  },
  amountChip: {
    flex: 1,
    paddingVertical: 12,
    paddingHorizontal: 8,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#D1D5DB',
    backgroundColor: '#F9FAFB',
    alignItems: 'center',
  },
  amountChipActive: {
    backgroundColor: '#111827',
    borderColor: '#111827',
  },
  amountChipText: {
    fontSize: 14,
    fontWeight: '800',
    color: '#111827',
  },
  amountChipTextActive: {
    color: '#FFFFFF',
  },
  input: {
    borderWidth: 1,
    borderColor: '#D1D5DB',
    borderRadius: 16,
    paddingHorizontal: 14,
    paddingVertical: 14,
    fontSize: 16,
    color: '#111827',
    backgroundColor: '#FFFFFF',
  },
  cardField: {
    width: '100%',
    height: 56,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#FFFFFF',
  },
  helperText: {
    fontSize: 12,
    lineHeight: 18,
    color: '#6B7280',
    fontWeight: '600',
  },
});
