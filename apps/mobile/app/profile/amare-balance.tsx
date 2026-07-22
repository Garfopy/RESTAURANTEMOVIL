import React, { useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Keyboard,
  RefreshControl,
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
import {
  assertStripeMinimumPaymentAmount,
  presentAmarePaymentSheet,
  showStripeMinimumAmountAlert,
  stripePaymentLabel,
} from '../../services/stripe-payment-sheet.service';

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
  const [walletLoading, setWalletLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadError, setLoadError] = useState(false);
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

  async function loadWallet(options?: { refreshing?: boolean }) {
    if (options?.refreshing) setRefreshing(true);
    else setWalletLoading(true);

    try {
      const nextWallet = await getRewardsWallet();
      setWallet(nextWallet);
      setLoadError(false);

      if (nextWallet.topup_options.length > 0) {
        setSelectedAmount((currentAmount) => {
          if (nextWallet.topup_options.some((option) => option.amount_mxn === currentAmount)) {
            return currentAmount;
          }

          topupRequestKeyRef.current = null;
          return nextWallet.topup_options[0].amount_mxn;
        });
      }
    } catch (error) {
      console.warn('No se pudo cargar Saldo Amare', error);
      setLoadError(true);
    } finally {
      setWalletLoading(false);
      setRefreshing(false);
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
      assertStripeMinimumPaymentAmount(selectedAmount);
      topupRequestKeyRef.current ??= `topup_${Date.now()}_${Math.random().toString(36).slice(2, 14)}`;
      const prepared = await createRewardsTopupIntent(selectedAmount, topupRequestKeyRef.current);
      await presentAmarePaymentSheet(stripe, {
        clientSecret: prepared.client_secret,
        amountMxn: prepared.amount_mxn,
      });
      paymentPresented = true;

      const nextWallet = await confirmRewardsTopup(prepared.payment_intent_id);
      topupRequestKeyRef.current = null;
      setWallet(nextWallet);
      Alert.alert('Recarga exitosa', `Tu Saldo Amare ahora es de $${nextWallet.balance_mxn.toFixed(2)}.`);
    } catch (error: any) {
      if (error?.name === 'PaymentCanceledError') return;
      if (showStripeMinimumAmountAlert(error)) return;
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

  const balance = Number(wallet?.balance_mxn ?? 0);
  const purchasedBalance = Number(wallet?.purchased_balance_mxn ?? 0);
  const promotionalBalance = Number(wallet?.promotional_balance_mxn ?? 0);
  const discountPercent = Math.round(Number(wallet?.discount_rate ?? 0.1) * 100);

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity style={styles.headerButton} onPress={handleBack} accessibilityRole="button" accessibilityLabel="Volver">
          <Ionicons name="arrow-back" size={23} color={Colors.text} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Saldo Amare</Text>
        <TouchableOpacity
          style={styles.headerButton}
          onPress={() => router.push('/profile/activity' as never)}
          accessibilityRole="button"
          accessibilityLabel="Ver movimientos"
        >
          <Ionicons name="receipt-outline" size={21} color={Colors.text} />
        </TouchableOpacity>
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="interactive"
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void loadWallet({ refreshing: true })} />}
      >
        <View style={styles.heroCard}>
          <View style={styles.heroTopRow}>
            <View>
              <Text style={styles.heroLabel}>SALDO DISPONIBLE</Text>
              {wallet ? (
                <Text style={styles.heroValue}>${balance.toFixed(2)}</Text>
              ) : walletLoading ? (
                <ActivityIndicator style={styles.heroLoader} color="#FFFFFF" />
              ) : (
                <Text style={[styles.heroValue, styles.heroValueUnavailable]}>--</Text>
              )}
            </View>
            <View style={styles.walletIcon}>
              <Ionicons name="wallet-outline" size={25} color="#F1D38B" />
            </View>
          </View>

          <View style={styles.benefitRow}>
            <Ionicons name="pricetag-outline" size={16} color="#F1D38B" />
            <Text style={styles.heroHint}>{discountPercent}% de descuento al pagar con Saldo Amare</Text>
          </View>

          <View style={styles.balanceBreakdown}>
            <View style={styles.balancePart}>
              <Text style={styles.balancePartLabel}>Comprado</Text>
              <Text style={styles.balancePartValue}>${purchasedBalance.toFixed(2)}</Text>
            </View>
            <View style={styles.balanceDivider} />
            <View style={styles.balancePart}>
              <Text style={styles.balancePartLabel}>Promocional</Text>
              <Text style={styles.balancePartValue}>${promotionalBalance.toFixed(2)}</Text>
            </View>
          </View>
        </View>

        {loadError && !wallet ? (
          <View style={styles.errorState}>
            <View style={styles.errorIcon}>
              <Ionicons name="cloud-offline-outline" size={22} color="#9F2D2D" />
            </View>
            <View style={styles.errorCopy}>
              <Text style={styles.errorTitle}>No pudimos consultar tu saldo</Text>
              <Text style={styles.errorText}>Revisa tu conexión e intenta nuevamente.</Text>
            </View>
            <TouchableOpacity style={styles.retryButton} onPress={() => void loadWallet()} accessibilityRole="button">
              <Ionicons name="refresh" size={19} color="#171A2C" />
            </TouchableOpacity>
          </View>
        ) : null}

        <View style={styles.section}>
          <View style={styles.sectionHeading}>
            <View>
              <Text style={styles.sectionEyebrow}>RECARGA</Text>
              <Text style={styles.sectionTitle}>Agrega saldo</Text>
            </View>
            <Text style={styles.sectionStep}>Pago seguro</Text>
          </View>
          <Text style={styles.sectionHint}>El saldo se acredita cuando Stripe confirma el pago.</Text>

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
                  accessibilityRole="radio"
                  accessibilityState={{ selected: active }}
                >
                  <Text style={[styles.amountChipText, active && styles.amountChipTextActive]}>
                    ${Number(option.amount_mxn).toLocaleString('es-MX')}
                  </Text>
                  {active ? <Ionicons name="checkmark-circle" size={17} color="#F1D38B" /> : null}
                </TouchableOpacity>
              );
            })}
          </View>

          {STRIPE_IS_CONFIGURED ? (
            <>
              <View style={styles.paymentMethod}>
                <View style={styles.paymentIcon}>
                  <Ionicons name="card-outline" size={22} color="#171A2C" />
                </View>
                <View style={styles.paymentCopy}>
                  <Text style={styles.paymentLabel}>Método de pago</Text>
                  <Text style={styles.paymentValue}>{stripePaymentLabel()}</Text>
                </View>
                <Ionicons name="shield-checkmark-outline" size={22} color="#12805C" />
              </View>

              <Button
                label={selectedAmount > 0
                  ? `Recargar $${Number(selectedAmount).toLocaleString('es-MX')}`
                  : 'Selecciona un monto para recargar'}
                onPress={handleTopup}
                fullWidth
                size="lg"
                loading={loading}
                disabled={loading || selectedAmount <= 0}
                style={styles.topupButton}
                textStyle={styles.topupButtonText}
              />

              <View style={styles.policyRow}>
                <Ionicons name="information-circle-outline" size={18} color="#6D6A63" />
                <Text style={styles.helperText}>
                  Úsalo en productos físicos de Amare. El saldo comprado no utilizado puede reembolsarse desde soporte.
                </Text>
              </View>
            </>
          ) : (
            <View style={styles.unavailableState}>
              <Ionicons name="card-outline" size={20} color="#8A5A16" />
              <Text style={styles.unavailableText}>Las recargas no están disponibles en este momento.</Text>
            </View>
          )}
        </View>

        <TouchableOpacity
          style={styles.activityLink}
          onPress={() => router.push('/profile/activity' as never)}
          activeOpacity={0.82}
          accessibilityRole="button"
        >
          <View style={styles.activityIcon}>
            <Ionicons name="swap-vertical" size={21} color="#171A2C" />
          </View>
          <View style={styles.activityCopy}>
            <Text style={styles.activityTitle}>Movimientos de saldo</Text>
            <Text style={styles.activityHint}>Recargas, compras y promociones</Text>
          </View>
          <Ionicons name="chevron-forward" size={20} color="#8E8E93" />
        </TouchableOpacity>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#F7F7F8' },
  flex: { flex: 1 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    minHeight: 60,
    paddingHorizontal: 16,
    paddingVertical: 8,
  },
  headerButton: {
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 22,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: Colors.text || '#111827',
  },
  content: {
    paddingHorizontal: 16,
    paddingTop: 4,
    paddingBottom: 44,
    gap: 22,
  },
  heroCard: {
    minHeight: 218,
    backgroundColor: '#171A2C',
    borderRadius: 22,
    padding: 22,
    overflow: 'hidden',
    ...Shadows.md,
  },
  heroTopRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
  },
  heroLabel: {
    fontSize: 11,
    fontWeight: '800',
    color: '#B8B9C3',
    letterSpacing: 0,
  },
  heroValue: {
    marginTop: 7,
    fontSize: 38,
    lineHeight: 45,
    fontWeight: '900',
    color: '#FFFFFF',
  },
  heroValueUnavailable: { color: '#B8B9C3' },
  heroLoader: { marginTop: 17, alignSelf: 'flex-start' },
  walletIcon: {
    width: 48,
    height: 48,
    borderRadius: 24,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(241,211,139,0.12)',
    borderWidth: 1,
    borderColor: 'rgba(241,211,139,0.22)',
  },
  benefitRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 13 },
  heroHint: {
    fontSize: 12,
    lineHeight: 18,
    color: '#F1D38B',
    fontWeight: '700',
  },
  balanceBreakdown: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 20,
    paddingTop: 17,
    borderTopWidth: 1,
    borderTopColor: 'rgba(255,255,255,0.12)',
  },
  balancePart: { flex: 1 },
  balancePartLabel: { color: '#999BA8', fontSize: 11, fontWeight: '700' },
  balancePartValue: { color: '#FFFFFF', fontSize: 15, fontWeight: '800', marginTop: 4 },
  balanceDivider: { width: 1, height: 34, backgroundColor: 'rgba(255,255,255,0.12)', marginHorizontal: 18 },
  errorState: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    borderRadius: 12,
    backgroundColor: '#FFF2F1',
    borderWidth: 1,
    borderColor: '#F3CFCC',
  },
  errorIcon: { width: 38, height: 38, borderRadius: 19, alignItems: 'center', justifyContent: 'center', backgroundColor: '#FFFFFF' },
  errorCopy: { flex: 1 },
  errorTitle: { color: '#722323', fontSize: 13, fontWeight: '800' },
  errorText: { color: '#945050', fontSize: 11, lineHeight: 16, marginTop: 2 },
  retryButton: { width: 38, height: 38, alignItems: 'center', justifyContent: 'center' },
  section: {
    gap: 14,
  },
  sectionHeading: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    justifyContent: 'space-between',
  },
  sectionEyebrow: { color: '#A36A13', fontSize: 10, fontWeight: '900', letterSpacing: 0 },
  sectionStep: { color: '#777780', fontSize: 11, fontWeight: '700' },
  sectionTitle: {
    marginTop: 3,
    fontSize: 22,
    lineHeight: 28,
    fontWeight: '900',
    color: '#171A2C',
  },
  sectionHint: {
    marginTop: -8,
    fontSize: 13,
    lineHeight: 19,
    color: '#707079',
    fontWeight: '600',
  },
  amountRow: {
    flexDirection: 'row',
    alignItems: 'stretch',
    gap: 10,
  },
  amountChip: {
    flex: 1,
    minHeight: 58,
    paddingVertical: 10,
    paddingHorizontal: 6,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#D9D9DE',
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 3,
  },
  amountChipActive: {
    backgroundColor: '#171A2C',
    borderColor: '#171A2C',
  },
  amountChipText: {
    fontSize: 14,
    fontWeight: '800',
    color: '#171A2C',
  },
  amountChipTextActive: {
    color: '#FFFFFF',
  },
  paymentMethod: {
    minHeight: 68,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E1E1E5',
    backgroundColor: '#FFFFFF',
    gap: 12,
  },
  paymentIcon: { width: 40, height: 40, borderRadius: 10, alignItems: 'center', justifyContent: 'center', backgroundColor: '#F1F1F4' },
  paymentCopy: { flex: 1 },
  paymentLabel: { color: '#777780', fontSize: 11, fontWeight: '700' },
  paymentValue: { color: '#171A2C', fontSize: 14, fontWeight: '900', marginTop: 3 },
  topupButton: { minHeight: 58, borderRadius: 12, backgroundColor: '#171A2C', borderColor: '#171A2C' },
  topupButtonText: { color: '#FFFFFF', fontSize: 16, fontWeight: '900' },
  policyRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 8, paddingHorizontal: 2 },
  helperText: {
    flex: 1,
    fontSize: 12,
    lineHeight: 18,
    color: '#6D6A63',
    fontWeight: '600',
  },
  unavailableState: { flexDirection: 'row', alignItems: 'center', gap: 10, padding: 14, borderRadius: 12, backgroundColor: '#FFF8E8' },
  unavailableText: { flex: 1, color: '#7A5118', fontSize: 12, lineHeight: 18, fontWeight: '700' },
  activityLink: {
    minHeight: 72,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 8,
    borderTopWidth: 1,
    borderTopColor: '#E1E1E5',
  },
  activityIcon: { width: 42, height: 42, borderRadius: 12, alignItems: 'center', justifyContent: 'center', backgroundColor: '#ECECF1' },
  activityCopy: { flex: 1 },
  activityTitle: { color: '#171A2C', fontSize: 14, fontWeight: '900' },
  activityHint: { color: '#777780', fontSize: 11, marginTop: 3 },
});
