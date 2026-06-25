import React, { useMemo, useState } from 'react';
import {
  Alert,
  Keyboard,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useFocusEffect, useRouter } from 'expo-router';
import { CardField, useStripe } from '@stripe/stripe-react-native';
import { Ionicons } from '@expo/vector-icons';
import { Button } from '../../components/ui/Button';
import { STRIPE_IS_CONFIGURED } from '../../constants/stripe';
import {
  MIN_REWARDS_TOPUP_AMOUNT,
  confirmRewardsTopup,
  createRewardsTopupIntent,
  getRewardsWallet,
  type RewardsTopupOption,
  type RewardsWallet,
} from '../../services/rewards.service';
import { Colors, Shadows } from '../../theme';

type TopupChoice = number | 'custom';

const MIN_TOPUP_AMOUNT = MIN_REWARDS_TOPUP_AMOUNT;

const FALLBACK_TOPUPS: RewardsTopupOption[] = [
  { amount_mxn: 500 },
  { amount_mxn: 1000 },
  { amount_mxn: 3000 },
];

export default function AmareBalanceScreen() {
  const router = useRouter();
  const { confirmPayment: stripeConfirm } = useStripe();
  const [wallet, setWallet] = useState<RewardsWallet | null>(null);
  const [loading, setLoading] = useState(false);
  const [selectedChoice, setSelectedChoice] = useState<TopupChoice>(500);
  const [customAmount, setCustomAmount] = useState('');
  const [cardComplete, setCardComplete] = useState(false);
  const [screenFocused, setScreenFocused] = useState(true);

  useFocusEffect(
    React.useCallback(() => {
      setScreenFocused(true);
      void loadWallet();
      return () => {
        Keyboard.dismiss();
        setScreenFocused(false);
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

  const selectedAmount = useMemo(() => {
    if (selectedChoice === 'custom') {
      const parsed = Number(customAmount);
      return Number.isFinite(parsed) && parsed > 0 ? Math.round(parsed) : 0;
    }
    return selectedChoice;
  }, [customAmount, selectedChoice]);

  const amountBelowMinimum = selectedAmount > 0 && selectedAmount < MIN_TOPUP_AMOUNT;

  async function handleTopup() {
    if (!STRIPE_IS_CONFIGURED) {
      Alert.alert('Stripe no disponible', 'La app no tiene Stripe configurado para recargar saldo.');
      return;
    }
    if (!selectedAmount || selectedAmount <= 0) {
      Alert.alert('Monto requerido', 'Ingresa un monto válido para recargar tu prepago.');
      return;
    }
    if (amountBelowMinimum) {
      Alert.alert('Monto minimo', `El monto minimo para recargar Saldo Amare es de $${MIN_TOPUP_AMOUNT} MXN.`);
      return;
    }
    if (!cardComplete) {
      Alert.alert('Tarjeta incompleta', 'Captura una tarjeta válida para continuar.');
      return;
    }

    setLoading(true);
    try {
      const prepared = await createRewardsTopupIntent(selectedAmount);
      const { error } = await stripeConfirm(prepared.client_secret, {
        paymentMethodType: 'Card',
      });

      if (error) {
        Alert.alert('Pago rechazado', error.message);
        return;
      }

      const nextWallet = await confirmRewardsTopup(prepared.payment_intent_id);
      setWallet(nextWallet);
      Alert.alert('Recarga exitosa', `Tu Saldo Amare ahora es de $${nextWallet.balance_mxn.toFixed(2)}.`);
    } catch (error: any) {
      Alert.alert('No se pudo recargar', error?.message || 'Intenta de nuevo en unos momentos.');
    } finally {
      setLoading(false);
    }
  }

  function handleBack() {
    Keyboard.dismiss();
    setCardComplete(false);
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

      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.heroCard}>
          <Text style={styles.heroLabel}>Disponible</Text>
          <Text style={styles.heroValue}>${Number(wallet?.balance_mxn ?? 0).toFixed(2)}</Text>
          <Text style={styles.heroHint}>Paga con tu prepago y recibe 10% de descuento directo.</Text>
        </View>

        <View style={styles.sectionCard}>
          <Text style={styles.sectionTitle}>Recargar prepago</Text>
          <Text style={styles.sectionHint}>Elige un monto rápido o usa “Otro” para personalizar la recarga.</Text>

          <View style={styles.amountRow}>
            {topupOptions.map((option) => {
              const active = selectedChoice === option.amount_mxn;
              return (
                <TouchableOpacity
                  key={option.amount_mxn}
                  style={[styles.amountChip, active && styles.amountChipActive]}
                  onPress={() => setSelectedChoice(option.amount_mxn)}
                  activeOpacity={0.88}
                >
                  <Text style={[styles.amountChipText, active && styles.amountChipTextActive]}>${option.amount_mxn}</Text>
                </TouchableOpacity>
              );
            })}

            <TouchableOpacity
              style={[styles.amountChip, selectedChoice === 'custom' && styles.amountChipActive]}
              onPress={() => setSelectedChoice('custom')}
              activeOpacity={0.88}
            >
              <Text style={[styles.amountChipText, selectedChoice === 'custom' && styles.amountChipTextActive]}>Otro</Text>
            </TouchableOpacity>
          </View>

          {selectedChoice === 'custom' ? (
            <TextInput
              value={customAmount}
              onChangeText={(value) => setCustomAmount(value.replace(/[^0-9]/g, ''))}
              inputMode="numeric"
              keyboardType="number-pad"
              placeholder="¿Cuánto quieres agregar?"
              placeholderTextColor="#9CA3AF"
              style={styles.input}
            />
          ) : null}
          {amountBelowMinimum ? (
            <Text style={styles.minimumHint}>El monto minimo de recarga es de $100 MXN.</Text>
          ) : null}

          {STRIPE_IS_CONFIGURED ? (
            <>
              {screenFocused ? (
                <CardField
                  postalCodeEnabled={false}
                  placeholders={{ number: '1234 5678 9012 3456' }}
                  cardStyle={{
                    backgroundColor: '#FFFFFF',
                    textColor: Colors.text || '#111827',
                    placeholderColor: '#9CA3AF',
                    borderRadius: 14,
                  }}
                  style={styles.cardField}
                  onCardChange={(details) => setCardComplete(Boolean(details.complete))}
                />
              ) : null}

              <Button
                label={
                  amountBelowMinimum
                    ? `Minimo $${MIN_TOPUP_AMOUNT} para recargar`
                    : selectedAmount > 0
                      ? `Recargar $${selectedAmount} con Stripe`
                      : 'Ingresa un monto para recargar'
                }
                onPress={handleTopup}
                fullWidth
                loading={loading}
                disabled={loading || !cardComplete || selectedAmount <= 0}
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
  minimumHint: {
    marginTop: -8,
    fontSize: 12,
    lineHeight: 18,
    color: '#B91C1C',
    fontWeight: '700',
  },
});
