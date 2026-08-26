import React, { useEffect, useRef, useState } from 'react';
import {
  Alert,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  TouchableOpacity,
  useWindowDimensions,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useStripe } from '@stripe/stripe-react-native';
import { useQueryClient } from '@tanstack/react-query';
import { Ionicons } from '@expo/vector-icons';
import { useCartStore } from '../../store/cart.store';
import { useBranchConfigStore, useBranchStore } from '../../store/branch.store';
import { useUserStore } from '../../store/user.store';
import { confirmPayment, createOrder, createPaymentIntent, getOrderById } from '../../services/orders.service';
import { getApiError } from '../../services/api';
import {
  EMPTY_FISCAL_DATA,
  buildInvoiceRequest,
  getFiscalData,
  validateFiscalData,
  type FiscalData,
} from '../../services/fiscal.service';
import { validatePromoCode, type PromotionQuote } from '../../services/promotions.service';
import { getRewardsWallet, quoteRewards, type RewardsQuote, type RewardsWallet } from '../../services/rewards.service';
import { Button } from '../../components/ui/Button';
import { STRIPE_IS_CONFIGURED } from '../../constants/stripe';
import { WALLET_ENABLED } from '../../constants/features';
import {
  assertStripeMinimumPaymentAmount,
  presentPaymentSheet,
  showStripeMinimumAmountAlert,
  STRIPE_MINIMUM_PAYMENT_MXN,
  stripePaymentLabel,
  stripePaymentSheetNote,
} from '../../services/stripe-payment-sheet.service';
import { InvoiceRequestForm } from '../../components/shared/InvoiceRequestForm';
import { Colors, Shadows, Spacing, Typography } from '../../theme';
import type { MetodoPagoHabilitado } from '@amare/types';

type PaymentMethod = 'card' | 'cash' | 'amare';
type PreparedCardPayment = {
  orderId: number;
  clientSecret: string;
  intentId: string;
  amount: number;
  status: string;
  usePoints: boolean;
  createdLocally: boolean;
};

interface PaymentMethodDef {
  id: PaymentMethod;
  label: string;
  icon: string;
  iconActive: string;
}

const isIOS = Platform.OS === 'ios';
const ALL_PAYMENT_METHODS: PaymentMethodDef[] = [
  { id: 'card', label: stripePaymentLabel(), icon: 'card-outline', iconActive: 'card' },
  { id: 'cash', label: 'Efectivo', icon: 'cash-outline', iconActive: 'cash' },
  { id: 'amare', label: 'Saldo', icon: 'sparkles-outline', iconActive: 'sparkles' },
];

function dbMethodToUI(m: MetodoPagoHabilitado): PaymentMethod {
  if (m === 'apple_pay' || m === 'google_pay') return 'card';
  return m as PaymentMethod;
}

export default function PaymentScreen() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const { width } = useWindowDimensions();
  const stripe = useStripe();
  const {
    restauranteId,
    tipoPedido,
    direccionId,
    direccionEntrega,
    orderId,
    amount,
  } = useLocalSearchParams<{
    restauranteId: string;
    tipoPedido: string;
    direccionId?: string;
    direccionEntrega?: string;
    orderId?: string;
    amount?: string;
  }>();

  const { items, total, clear, restauranteId: cartRestaurantId } = useCartStore();
  const user = useUserStore((s) => s.user);
  const selectedBranchId = useBranchStore((s) => s.seleccionada?.id);
  const resolvedRestaurantId =
    Number(restauranteId) ||
    cartRestaurantId ||
    selectedBranchId ||
    items[0]?.platillo?.restaurante_id ||
    null;

  const existingOrderId = typeof orderId === 'string' && orderId !== '' ? Number(orderId) : null;
  const parsedAmount = typeof amount === 'string' && amount !== '' ? Number(amount) : NaN;
  const paymentAmount = Number.isFinite(parsedAmount) && parsedAmount > 0 ? parsedAmount : total;
  const [loading, setLoading] = useState(false);
  const paymentLockRef = useRef(false);
  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod>('card');
  const [useRewardsPoints, setUseRewardsPoints] = useState(false);
  const [rewardsWallet, setRewardsWallet] = useState<RewardsWallet | null>(null);
  const [rewardsQuote, setRewardsQuote] = useState<RewardsQuote | null>(null);
  const [rewardsLoading, setRewardsLoading] = useState(false);
  const [couponCode, setCouponCode] = useState('');
  const [couponQuote, setCouponQuote] = useState<PromotionQuote | null>(null);
  const [couponLoading, setCouponLoading] = useState(false);
  const [invoiceRequired, setInvoiceRequired] = useState(false);
  const [invoiceSaveToProfile, setInvoiceSaveToProfile] = useState(true);
  const [invoiceFiscalData, setInvoiceFiscalData] = useState<FiscalData>(EMPTY_FISCAL_DATA);
  const [pickupPhone, setPickupPhone] = useState('');
  const [preparedCardPayment, setPreparedCardPayment] = useState<PreparedCardPayment | null>(null);
  const isPickupOrder = tipoPedido === 'pickup';

  const config = useBranchConfigStore((state) => (state.branchId === resolvedRestaurantId ? state.config : null));
  const refreshBranchConfig = useBranchConfigStore((state) => state.refresh);
  const invoiceEnabled = Boolean(config?.facturacion?.habilitada);

  const enabledMethodIds: PaymentMethod[] = (
    config
      ? [...new Set<PaymentMethod>([...config.metodos_pago.map(dbMethodToUI), 'amare'])]
      : (['card', 'cash', 'amare'] as PaymentMethod[])
  ).filter((id) => id !== 'amare' || WALLET_ENABLED);

  const enabledMethods = ALL_PAYMENT_METHODS.filter(
    (method) =>
      enabledMethodIds.includes(method.id) &&
      (method.id !== 'card' || STRIPE_IS_CONFIGURED)
  );

  useEffect(() => {
    if (!resolvedRestaurantId) return;
    void refreshBranchConfig(Number(resolvedRestaurantId)).catch((err) =>
      console.error('Error al cargar configuracion:', err)
    );
  }, [refreshBranchConfig, resolvedRestaurantId]);

  useEffect(() => {
    if (!config) return;
    const ids = [...new Set<PaymentMethod>([...config.metodos_pago.map(dbMethodToUI), 'amare'])]
      .filter((id) => id !== 'card' || STRIPE_IS_CONFIGURED)
      .filter((id) => id !== 'amare' || WALLET_ENABLED);
    if (!ids.includes(selectedMethod)) {
      setSelectedMethod(ids[0] ?? 'cash');
    }
  }, [config, selectedMethod]);

  useEffect(() => {
    if (!invoiceEnabled) {
      setInvoiceRequired(false);
      return;
    }

    let cancelled = false;
    async function loadFiscalData() {
      const saved = await getFiscalData().catch(() => null);
      if (!cancelled && saved) {
        setInvoiceFiscalData(saved);
      }
    }

    void loadFiscalData();
    return () => {
      cancelled = true;
    };
  }, [invoiceEnabled]);

  useEffect(() => {
    if (!WALLET_ENABLED) return;
    let cancelled = false;

    async function loadRewards() {
      try {
        const wallet = await getRewardsWallet();
        if (!cancelled) setRewardsWallet(wallet);
      } catch (error) {
        console.warn('No se pudo cargar el saldo', error);
      }
    }

    void loadRewards();
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (!isPickupOrder) return;
    setPickupPhone((current) => current || normalizePhone(String(user?.telefono ?? '')));
  }, [isPickupOrder, user?.telefono]);

  const rewardsPaymentMode = selectedMethod === 'amare' ? 'wallet' : selectedMethod === 'card' ? 'stripe' : 'external';
  const couponDiscount = Math.max(0, Number(couponQuote?.discount ?? 0));
  const promoAdjustedAmount = Math.max(0, Math.round((paymentAmount - couponDiscount) * 100) / 100);

  useEffect(() => {
    if (!WALLET_ENABLED) return;
    let cancelled = false;

    async function loadQuote() {
      if (promoAdjustedAmount <= 0) {
        setRewardsQuote(null);
        return;
      }

      setRewardsLoading(true);
      try {
        const quote = await quoteRewards({
          context: 'food',
          amount: promoAdjustedAmount,
          use_points: useRewardsPoints,
          payment_mode: rewardsPaymentMode,
          items: items.map((item) => ({
            quantity: item.cantidad,
            unit_price: item.precio_unitario,
          })),
        });
        if (!cancelled) setRewardsQuote(quote);
      } catch (error) {
        console.warn('No se pudo cotizar el saldo', error);
        if (!cancelled) setRewardsQuote(null);
      } finally {
        if (!cancelled) setRewardsLoading(false);
      }
    }

    void loadQuote();
    return () => {
      cancelled = true;
    };
  }, [items, promoAdjustedAmount, rewardsPaymentMode, useRewardsPoints]);

  const availablePoints = Number(rewardsWallet?.points ?? rewardsQuote?.points ?? 0);
  const walletBalance = Number(rewardsWallet?.balance_mxn ?? rewardsQuote?.balance_mxn ?? 0);
  const methodDiscount = selectedMethod === 'amare' ? Math.round(promoAdjustedAmount * 0.1 * 100) / 100 : 0;
  const totalAfterMethodDiscount = Math.max(0, Math.round((promoAdjustedAmount - methodDiscount) * 100) / 100);
  const maximumPointsForMethod =
    selectedMethod === 'card'
      ? Math.max(0, Math.floor(Math.max(0, totalAfterMethodDiscount - STRIPE_MINIMUM_PAYMENT_MXN)))
      : Math.floor(totalAfterMethodDiscount);
  const quotedPointsApplied = useRewardsPoints && rewardsQuote ? Number(rewardsQuote.points_redeemed ?? 0) : null;
  const pointsApplied = useRewardsPoints
    ? quotedPointsApplied ?? Math.min(availablePoints, maximumPointsForMethod)
    : 0;
  const pointsDiscount = pointsApplied;
  const effectivePaymentAmount = Math.max(0, Math.round((totalAfterMethodDiscount - pointsDiscount) * 100) / 100);
  const displayedPaymentAmount = preparedCardPayment?.amount ?? effectivePaymentAmount;
  const serverPriceAdjustment = Math.round((displayedPaymentAmount - effectivePaymentAmount) * 100) / 100;
  const pointsEarned = selectedMethod === 'amare' ? 0 : Math.max(0, Math.round(displayedPaymentAmount * 0.05));
  const walletPreviewDiscount = Math.round(promoAdjustedAmount * 0.1 * 100) / 100;
  const walletPreviewTotalAfterDiscount = Math.max(0, Math.round((promoAdjustedAmount - walletPreviewDiscount) * 100) / 100);
  const walletPreviewPoints = useRewardsPoints ? Math.min(availablePoints, Math.floor(walletPreviewTotalAfterDiscount)) : 0;
  const walletPreviewTotal = Math.max(0, Math.round((walletPreviewTotalAfterDiscount - walletPreviewPoints) * 100) / 100);
  const canPayWithAmare = walletBalance >= walletPreviewTotal;

  useEffect(() => {
    setCouponQuote(null);
  }, [items, paymentAmount]);

  function getCardWidth() {
    const count = enabledMethods.length;
    const containerPadding = Spacing.base * 2;
    const gap = 10;
    const available = width - containerPadding;

    if (count === 1) return Math.min(available * 0.55, 200);
    if (count === 2) return (available - gap) / 2;
    return (available - gap * (count - 1)) / count;
  }

  function promoItemsPayload() {
    return items.map((item) => ({
      product_id: item.platillo.id,
      quantity: item.cantidad,
      unit_price: item.precio_unitario,
      origen: 'menu',
    }));
  }

  async function resolvePromoItemsPayload() {
    const cartItems = promoItemsPayload();
    if (cartItems.length > 0 || !existingOrderId) {
      return cartItems;
    }

    const order = await getOrderById(existingOrderId);
    return (order.items ?? []).map((item) => ({
      product_id: item.platillo_id,
      quantity: item.cantidad,
      unit_price: item.precio_unit,
      origen: item.origen ?? 'menu',
    }));
  }

  async function applyCoupon(): Promise<PromotionQuote | null> {
    const code = couponCode.trim();
    if (!code) {
      Alert.alert('Código requerido', 'Escribe el código de la promoción.');
      return null;
    }

    setCouponLoading(true);
    try {
      const promoItems = await resolvePromoItemsPayload();
      if (promoItems.length === 0) {
        Alert.alert('Sin productos', 'No se detectaron productos para validar este cupón.');
        return null;
      }

      const quote = await validatePromoCode(code, promoItems);
      setCouponQuote(quote);
      setCouponCode(quote.code ?? code.toUpperCase());
      return quote;
    } catch (error) {
      setCouponQuote(null);
      Alert.alert('Cupón no válido', getApiError(error) || 'Este código no es válido para tu carrito.');
      return null;
    } finally {
      setCouponLoading(false);
    }
  }

  function clearCoupon() {
    setCouponQuote(null);
    setCouponCode('');
  }

  async function handlePay() {
    if (paymentLockRef.current) return;
    paymentLockRef.current = true;
    setLoading(true);
    let stripeCompletedOrderId: number | null = null;
    try {
      if (!resolvedRestaurantId || Number.isNaN(Number(resolvedRestaurantId))) {
        throw new Error('No se detectó la sucursal del pedido. Regresa al carrito e intenta de nuevo.');
      }

      if (couponCode.trim() !== '' && !couponQuote) {
        const quote = await applyCoupon();
        if (!quote) return;
        Alert.alert('Cupón aplicado', 'Revisa el nuevo total y vuelve a tocar Pagar.');
        return;
      }

      const invoiceValidation = invoiceRequired ? validateFiscalData(invoiceFiscalData) : null;
      if (invoiceValidation) {
        Alert.alert('Datos fiscales incompletos', invoiceValidation);
        return;
      }
      const invoiceRequest = buildInvoiceRequest(invoiceRequired, invoiceFiscalData, invoiceSaveToProfile);

      if (isPickupOrder) {
        const phone = normalizePhone(pickupPhone || String(user?.telefono ?? ''));
        if (!phone || phone.length !== 10) {
          Alert.alert('Telefono requerido', 'Ingresa un telefono de 10 digitos para avisarte cuando tu pedido este listo.');
          return;
        }
      }

      if (selectedMethod === 'cash') {
        const order = existingOrderId ? null : await createOrderBackend('cash');
        const targetOrderId = existingOrderId ?? order!.id;
        await confirmPayment({
          pedido_id: targetOrderId,
          payment_intent_id: '',
          metodo: 'cash',
          use_points: useRewardsPoints,
          promo_code: couponQuote?.code ?? (couponCode.trim() || undefined),
          invoice_request: invoiceRequest,
        });
        if (!existingOrderId) clear();
        await refreshRewardsWallet();
        showInvoiceReceived(invoiceRequest !== null);
        await finishOrderFlow(targetOrderId);
        return;
      }

      if (selectedMethod === 'amare') {
        if (!canPayWithAmare) {
          throw new Error('Tu saldo no alcanza para cubrir este pago.');
        }
        const order = existingOrderId ? null : await createOrderBackend('amare_wallet');
        const targetOrderId = existingOrderId ?? order!.id;
        await confirmPayment({
          pedido_id: targetOrderId,
          metodo: 'amare_wallet',
          use_points: useRewardsPoints,
          promo_code: couponQuote?.code ?? (couponCode.trim() || undefined),
          invoice_request: invoiceRequest,
        });
        if (!existingOrderId) clear();
        await refreshRewardsWallet();
        showInvoiceReceived(invoiceRequest !== null);
        await finishOrderFlow(targetOrderId);
        return;
      }

      if (selectedMethod === 'card') {
        if (!STRIPE_IS_CONFIGURED) {
          throw new Error('Stripe no esta configurado para este APK. Revisa EXPO_PUBLIC_STRIPE_KEY en EAS.');
        }
        assertStripeMinimumPaymentAmount(effectivePaymentAmount);

        let prepared = preparedCardPayment;
        if (!prepared) {
          const order = existingOrderId ? await getOrderById(existingOrderId) : await createOrderBackend('card');
          const paymentIntent = await resolvePaymentIntent(order.id, existingOrderId !== null, invoiceRequest);
          prepared = {
            orderId: order.id,
            clientSecret: paymentIntent.clientSecret,
            intentId: paymentIntent.intentId,
            amount: paymentIntent.amount,
            status: paymentIntent.status,
            usePoints: paymentIntent.usePoints,
            createdLocally: !existingOrderId,
          };
          setPreparedCardPayment(prepared);
          if (prepared.usePoints !== useRewardsPoints) {
            setUseRewardsPoints(prepared.usePoints);
          }

          if (Math.abs(paymentIntent.amount - effectivePaymentAmount) >= 0.01 || prepared.usePoints !== useRewardsPoints) {
            Alert.alert(
              'Total actualizado',
              `El total vigente es $${paymentIntent.amount.toFixed(2)} MXN. Revisa el importe actualizado y vuelve a tocar Pagar para confirmarlo.`
            );
            return;
          }
        }

        if (!prepared.clientSecret) {
          throw new Error('No se recibio el cliente de pago de Stripe. Intenta de nuevo.');
        }
        if (prepared.status !== 'succeeded') {
          await presentPaymentSheet(stripe, {
            clientSecret: prepared.clientSecret,
            amountMxn: prepared.amount,
            customerName: user?.nombre,
            customerEmail: user?.email,
          });
        }
        stripeCompletedOrderId = prepared.orderId;

        await confirmPayment({
          pedido_id: prepared.orderId,
          payment_intent_id: prepared.intentId,
          metodo: 'card',
          use_points: prepared.usePoints,
          invoice_request: invoiceRequest,
        });
        stripeCompletedOrderId = null;
        if (prepared.createdLocally) clear();
        await refreshRewardsWallet();
        showInvoiceReceived(invoiceRequest !== null);
        await finishOrderFlow(prepared.orderId);
        return;
      }

    } catch (err: any) {
      if (err?.name === 'PaymentCanceledError') return;
      if (showStripeMinimumAmountAlert(err)) return;
      if (stripeCompletedOrderId) {
        Alert.alert(
          'Procesando pago',
          'Stripe recibio el pago, pero la confirmacion se interrumpio. Conservaremos tu carrito y verificaremos el resultado automaticamente.'
        );
        router.replace({ pathname: '/order/[id]', params: { id: String(stripeCompletedOrderId) } });
        return;
      }
      Alert.alert('Error', getApiError(err) || err.message || 'No se pudo procesar el pago.');
      console.error('Error detallado en handlePay:', err);
    } finally {
      paymentLockRef.current = false;
      setLoading(false);
    }
  }

  async function refreshRewardsWallet() {
    const wallet = await getRewardsWallet().catch(() => null);
    if (wallet) {
      setRewardsWallet(wallet);
    }
  }

  async function finishOrderFlow(targetOrderId: number) {
    refreshRealtimeState(queryClient);
    router.replace({ pathname: '/order/[id]', params: { id: String(targetOrderId) } });
  }

  async function resolvePaymentIntent(
    orderId: number,
    applyPromoToExistingOrder: boolean,
    invoiceRequest: ReturnType<typeof buildInvoiceRequest>
  ): Promise<{ clientSecret: string; intentId: string; amount: number; status: string; usePoints: boolean }> {
    const paymentIntent = await createPaymentIntent({
      order_id: orderId,
      currency: 'mxn',
      promo_code: applyPromoToExistingOrder
        ? couponQuote?.code ?? (couponCode.trim() || undefined)
        : undefined,
      use_points: useRewardsPoints,
      invoice_request: invoiceRequest,
    });

    return {
      clientSecret: paymentIntent.client_secret,
      intentId: paymentIntent.id,
      amount: paymentIntent.amount,
      status: paymentIntent.status,
      usePoints: Boolean(paymentIntent.use_points),
    };
  }

  async function createOrderBackend(metodoPago: string, paymentIntentId?: string) {
    return await createOrder({
      restaurante_id: Number(resolvedRestaurantId),
      tipo_pedido: tipoPedido as never,
      direccion_id: typeof direccionId === 'string' && direccionId !== '' ? Number(direccionId) : undefined,
      direccion_entrega:
        typeof direccionEntrega === 'string' && direccionEntrega !== '' ? direccionEntrega : undefined,
      items: items.map((item) => ({
        platillo_id: item.platillo.id,
        cantidad: item.cantidad,
        precio_unit: item.precio_unitario,
        notas: item.notas,
        modificadores: item.modificadores_seleccionados.map((modifier) => ({
          modificador_id: modifier.modificador_id,
          modificador_nombre: modifier.modificador_nombre,
          opciones: modifier.opciones.map((option) => ({
            opcion_id: option.opcion_id,
            opcion_nombre: option.opcion_nombre,
            precio_extra: option.precio_extra,
            cantidad: option.cantidad,
            tipo_modificador: option.tipo_modificador,
          })),
        })),
      })),
      payment_intent_id: paymentIntentId || undefined,
      promo_code: couponQuote?.code ?? undefined,
      notas: isPickupOrder
        ? `Pago via: ${metodoPago} | Telefono pickup: ${normalizePhone(pickupPhone || String(user?.telefono ?? ''))}`
        : `Pago via: ${metodoPago}`,
    });
  }

  function showInvoiceReceived(enabled: boolean) {
    if (enabled) {
      Alert.alert('Solicitud de factura recibida', 'La sucursal recibio tus datos fiscales para procesarla.');
    }
  }

  const cardWidth = getCardWidth();
  const count = enabledMethods.length;

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity
          onPress={() => router.back()}
          accessibilityLabel="Volver atras"
          accessibilityRole="button"
          testID="back-btn"
        >
          <Ionicons name="arrow-back" size={24} color={Colors.text} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Método de Pago</Text>
        <View style={{ width: 24 }} />
      </View>

      <KeyboardAvoidingView behavior={isIOS ? 'padding' : 'height'} style={styles.flex}>
      <ScrollView
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="interactive"
      >
        <Text style={styles.sectionLabel}>Selecciona cómo quieres pagar</Text>

        <View style={[styles.methodsContainer, count === 1 && styles.methodsContainerCentered]}>
          {enabledMethods.map((method) => {
            const isSelected = selectedMethod === method.id;
            const isAmareDisabled = method.id === 'amare' && !canPayWithAmare;

            return (
              <TouchableOpacity
                key={method.id}
                style={[
                  styles.methodCard,
                  { width: cardWidth, aspectRatio: count === 1 ? 1.2 : 1 },
                  isSelected && styles.methodCardActive,
                  isAmareDisabled && styles.methodCardDisabled,
                ]}
                onPress={() => setSelectedMethod(method.id)}
                disabled={isAmareDisabled || preparedCardPayment !== null}
                accessibilityLabel={`Pagar con ${method.label}`}
                accessibilityRole="radio"
                accessibilityState={{ selected: isSelected }}
                testID={`payment-method-${method.id}`}
              >
                <Ionicons
                  name={(isSelected ? method.iconActive : method.icon) as never}
                  size={count === 1 ? 32 : 26}
                  color={isSelected ? Colors.primary : Colors.textMuted}
                />
                <Text numberOfLines={2} style={[styles.methodText, isSelected && styles.methodTextActive]}>
                  {method.label}
                </Text>
              </TouchableOpacity>
            );
          })}
        </View>

        {selectedMethod === 'card' && STRIPE_IS_CONFIGURED ? (
          <View style={styles.stripeContainer}>
            <Text style={styles.secureNote}>
              <Ionicons name="lock-closed-outline" size={12} color={Colors.success} /> {stripePaymentSheetNote()}
            </Text>
          </View>
        ) : null}

        {isPickupOrder ? (
          <View style={styles.pickupContactBox}>
            <Text style={styles.sectionLabel}>Contacto para recoger</Text>
            <TextInput
              value={pickupPhone}
              onChangeText={(value) => setPickupPhone(normalizePhone(value))}
              placeholder="Telefono de 10 digitos"
              placeholderTextColor={Colors.textMuted}
              keyboardType="phone-pad"
              maxLength={10}
              style={styles.pickupPhoneInput}
            />
            <Text style={styles.pickupContactHint}>Te avisaremos cuando el pedido este listo en mostrador.</Text>
          </View>
        ) : null}

        <View style={styles.couponBox}>
            <View style={styles.couponHeader}>
              <View style={styles.couponIcon}>
                <Ionicons name="pricetag-outline" size={18} color={Colors.primary} />
              </View>
              <View style={styles.couponCopy}>
                <Text style={styles.couponTitle}>Cupón de promoción</Text>
                <Text style={styles.couponSubtitle}>Aplica solo si el producto de la promo está en tu pedido.</Text>
              </View>
            </View>

            <View style={styles.couponForm}>
              <TextInput
                value={couponCode}
                onChangeText={(text) => {
                  setCouponCode(text.toUpperCase());
                  setCouponQuote(null);
                }}
                autoCapitalize="characters"
                placeholder="CODIGO"
                placeholderTextColor={Colors.textMuted}
                style={styles.couponInput}
                editable={!couponLoading && preparedCardPayment === null}
              />
              {couponQuote ? (
                <TouchableOpacity
                  style={styles.couponClearButton}
                  onPress={clearCoupon}
                  disabled={preparedCardPayment !== null}
                >
                  <Ionicons name="close" size={18} color={Colors.textMuted} />
                </TouchableOpacity>
              ) : (
                <TouchableOpacity
                  style={styles.couponButton}
                  onPress={() => void applyCoupon()}
                  disabled={couponLoading || preparedCardPayment !== null}
                >
                  <Text style={styles.couponButtonText}>{couponLoading ? '...' : 'Aplicar'}</Text>
                </TouchableOpacity>
              )}
            </View>

            {couponQuote ? (
              <View style={styles.couponApplied}>
                <Text style={styles.couponAppliedTitle}>{couponQuote.promotion.titulo}</Text>
                <Text style={styles.couponAppliedText}>Descuento aplicado: -${couponDiscount.toFixed(2)}</Text>
              </View>
            ) : null}
        </View>

        {WALLET_ENABLED ? (
        <View style={styles.pointsBox}>
          <View style={styles.pointsHeader}>
            <View style={{ flex: 1 }}>
              <Text style={styles.pointsTitle}>Puntos</Text>
              <Text style={styles.pointsSubtitle}>1 punto = 1 peso. Puedes activarlos o quitarlos para este pedido.</Text>
            </View>
            {availablePoints > 0 ? (
              <Switch
                value={useRewardsPoints}
                onValueChange={setUseRewardsPoints}
                disabled={preparedCardPayment !== null}
                trackColor={{ false: Colors.border, true: Colors.accentLight }}
                thumbColor={useRewardsPoints ? Colors.primary : Colors.surface}
              />
            ) : null}
          </View>

          <View style={styles.pointsRows}>
            <View style={styles.pointsRow}>
              <Text style={styles.pointsLabel}>Disponibles</Text>
              <Text style={styles.pointsValue}>{availablePoints} pts</Text>
            </View>
            {useRewardsPoints ? (
              <View style={styles.pointsRow}>
                <Text style={styles.pointsLabel}>Aplicados</Text>
                <Text style={styles.pointsValue}>-${pointsApplied.toFixed(2)}</Text>
              </View>
            ) : null}
            {selectedMethod !== 'amare' ? (
              <View style={styles.pointsRow}>
                <Text style={styles.pointsLabel}>Puntos que recibes</Text>
                <Text style={styles.pointsValue}>+{pointsEarned} pts</Text>
              </View>
            ) : null}
          </View>

          {availablePoints <= 0 ? (
            <Text style={styles.pointsHint}>Aún no tienes puntos disponibles para usar en este pedido.</Text>
          ) : selectedMethod === 'amare' ? (
            <Text style={styles.pointsHint}>Con este saldo obtienes 10% de descuento directo.</Text>
          ) : selectedMethod === 'card' && useRewardsPoints && rewardsQuote?.points_limited_by_minimum ? (
            <Text style={styles.pointsHint}>Limitamos los puntos para dejar el minimo de $10.00 MXN requerido por Stripe.</Text>
          ) : (
            <Text style={styles.pointsHint}>Si no pagas con tu saldo, recibes 5% del total pagado en puntos.</Text>
          )}
        </View>
        ) : null}

        {WALLET_ENABLED && selectedMethod === 'amare' ? (
          <View style={styles.rewardsBox}>
            <View style={styles.rewardsHeader}>
              <View style={styles.rewardsHeaderCopy}>
                <Text style={styles.rewardsTitle}>Saldo</Text>
                <Text style={styles.rewardsSubtitle}>Tu prepago aplica 10% de descuento en este pedido</Text>
              </View>
              <Text style={styles.rewardsBalance} numberOfLines={1} adjustsFontSizeToFit>
                ${Number(rewardsWallet?.balance_mxn ?? rewardsQuote?.balance_mxn ?? 0).toFixed(2)}
              </Text>
            </View>

            <View style={styles.rewardsRows}>
              <View style={styles.rewardsRow}>
                <Text style={styles.rewardsLabel}>Subtotal</Text>
                <Text style={styles.rewardsValue}>${promoAdjustedAmount.toFixed(2)}</Text>
              </View>
              <View style={styles.rewardsRow}>
                <Text style={styles.rewardsLabel}>Descuento por saldo</Text>
                <Text style={styles.rewardsValue}>-${methodDiscount.toFixed(2)}</Text>
              </View>
              {useRewardsPoints ? (
                <View style={styles.rewardsRow}>
                  <Text style={styles.rewardsLabel}>Puntos usados</Text>
                  <Text style={styles.rewardsValue}>-${pointsDiscount.toFixed(2)}</Text>
                </View>
              ) : null}
              <View style={styles.rewardsRow}>
                <Text style={styles.rewardsLabel}>Saldo a usar</Text>
                <Text style={styles.rewardsValue}>${displayedPaymentAmount.toFixed(2)}</Text>
              </View>
            </View>

            {!canPayWithAmare ? (
              <Text style={styles.rewardsWarning}>Tu saldo no alcanza para cubrir este pago.</Text>
            ) : null}
          </View>
        ) : null}

        <InvoiceRequestForm
          enabled={invoiceEnabled}
          required={invoiceRequired}
          data={invoiceFiscalData}
          saveToProfile={invoiceSaveToProfile}
          disabled={loading}
          onRequiredChange={setInvoiceRequired}
          onDataChange={setInvoiceFiscalData}
          onSaveToProfileChange={setInvoiceSaveToProfile}
        />

        <View style={styles.totalBox}>
          <View style={styles.totalRows}>
            <View style={styles.totalRow}>
              <Text style={styles.totalLabel}>Subtotal</Text>
              <Text style={styles.totalLineValue}>${paymentAmount.toFixed(2)}</Text>
            </View>
            {couponDiscount > 0 ? (
              <View style={styles.totalRow}>
                <Text style={styles.totalLabel}>Cupón</Text>
                <Text style={styles.totalDiscountValue}>-${couponDiscount.toFixed(2)}</Text>
              </View>
            ) : null}
            {methodDiscount > 0 ? (
              <View style={styles.totalRow}>
                <Text style={styles.totalLabel}>Descuento por saldo</Text>
                <Text style={styles.totalDiscountValue}>-${methodDiscount.toFixed(2)}</Text>
              </View>
            ) : null}
            {pointsDiscount > 0 ? (
              <View style={styles.totalRow}>
                <Text style={styles.totalLabel}>Puntos</Text>
                <Text style={styles.totalDiscountValue}>-${pointsDiscount.toFixed(2)}</Text>
              </View>
            ) : null}
            {Math.abs(serverPriceAdjustment) >= 0.01 ? (
              <View style={styles.totalRow}>
                <Text style={styles.totalLabel}>Actualizacion de precios</Text>
                <Text style={serverPriceAdjustment > 0 ? styles.totalLineValue : styles.totalDiscountValue}>
                  {serverPriceAdjustment > 0 ? '+' : '-'}${Math.abs(serverPriceAdjustment).toFixed(2)}
                </Text>
              </View>
            ) : null}
            <View style={[styles.totalRow, styles.totalFinalRow]}>
              <Text style={styles.totalFinalLabel}>Total a pagar</Text>
              <Text style={styles.totalValue}>${displayedPaymentAmount.toFixed(2)} MXN</Text>
            </View>
          </View>
        </View>
      </ScrollView>

      <View style={styles.footer}>
        <Button
          label={
            selectedMethod === 'cash'
              ? `Confirmar pago ($${displayedPaymentAmount.toFixed(2)})`
              : selectedMethod === 'amare'
                ? `Pagar con saldo ($${displayedPaymentAmount.toFixed(2)})`
                : `Pagar $${displayedPaymentAmount.toFixed(2)}`
          }
          onPress={handlePay}
          fullWidth
          size="lg"
          loading={loading}
          disabled={selectedMethod === 'amare' && (!canPayWithAmare || rewardsLoading)}
          accessibilityLabel={
            selectedMethod === 'cash'
              ? `Confirmar pago por $${displayedPaymentAmount.toFixed(2)} en efectivo`
              : selectedMethod === 'amare'
                ? `Pagar $${displayedPaymentAmount.toFixed(2)} con Saldo`
                : `Pagar $${displayedPaymentAmount.toFixed(2)} con ${selectedMethod === 'card' ? 'tarjeta' : 'billetera digital'}`
          }
          testID="payment-confirm-btn"
        />
      </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

function refreshRealtimeState(queryClient: ReturnType<typeof useQueryClient>): void {
  void queryClient.invalidateQueries({ queryKey: ['orders'] });
  void queryClient.invalidateQueries({ queryKey: ['social'] });
}

function normalizePhone(value: string): string {
  const digits = value.replace(/\D+/g, '');
  if (digits.length === 12 && digits.startsWith('52')) {
    return digits.slice(2);
  }
  if (digits.length === 13 && digits.startsWith('521')) {
    return digits.slice(3);
  }
  return digits.length > 10 ? digits.slice(-10) : digits;
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  flex: { flex: 1 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.base,
    paddingVertical: Spacing.sm,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  headerTitle: { ...Typography.h3, fontWeight: '700', color: Colors.text },
  content: { padding: Spacing.base, paddingBottom: 120, gap: Spacing.xl },
  sectionLabel: { fontSize: 16, fontWeight: '600', color: Colors.text, marginBottom: 4 },
  methodsContainer: {
    flexDirection: 'row',
    gap: 10,
    justifyContent: 'space-between',
    width: '100%',
  },
  methodsContainerCentered: {
    justifyContent: 'center',
  },
  methodCard: {
    flexDirection: 'column',
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: Colors.surface,
    padding: 8,
    borderRadius: 16,
    borderWidth: 2,
    borderColor: '#E5E7EB',
    ...Shadows.sm,
  },
  methodCardActive: {
    borderColor: Colors.primary,
    backgroundColor: `${Colors.primary}08`,
  },
  methodCardDisabled: {
    opacity: 0.45,
  },
  methodText: {
    fontSize: 12,
    fontWeight: '600',
    color: Colors.textMuted,
    textAlign: 'center',
    marginTop: 10,
    paddingHorizontal: 2,
  },
  methodTextActive: {
    color: Colors.primary,
    fontWeight: '700',
  },
  stripeContainer: { gap: Spacing.sm, marginTop: Spacing.sm },
  cardField: {
    width: '100%',
    height: 56,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: Colors.border,
  },
  secureNote: { fontSize: 12, color: Colors.success, textAlign: 'center', marginTop: 4 },
  pickupContactBox: {
    gap: 8,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: Colors.surface,
    padding: Spacing.md,
  },
  pickupPhoneInput: {
    minHeight: 48,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: Colors.border,
    backgroundColor: Colors.background,
    paddingHorizontal: 12,
    fontSize: 15,
    fontWeight: '800',
    color: Colors.text,
  },
  pickupContactHint: {
    fontSize: 12,
    color: Colors.textMuted,
    fontWeight: '600',
    lineHeight: 17,
  },
  couponBox: {
    gap: 12,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: Colors.surface,
    padding: Spacing.md,
  },
  couponHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  couponIcon: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: `${Colors.primary}14`,
    alignItems: 'center',
    justifyContent: 'center',
  },
  couponCopy: {
    flex: 1,
    minWidth: 0,
  },
  couponTitle: {
    fontSize: 15,
    fontWeight: '800',
    color: Colors.text,
  },
  couponSubtitle: {
    marginTop: 2,
    fontSize: 12,
    lineHeight: 17,
    fontWeight: '600',
    color: Colors.textMuted,
  },
  couponForm: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  couponInput: {
    flex: 1,
    minHeight: 46,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: Colors.border,
    backgroundColor: Colors.background,
    paddingHorizontal: 12,
    fontSize: 15,
    fontWeight: '800',
    color: Colors.text,
  },
  couponButton: {
    height: 46,
    paddingHorizontal: 16,
    borderRadius: 12,
    backgroundColor: Colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  couponButtonText: {
    fontSize: 13,
    fontWeight: '900',
    color: '#FFFFFF',
  },
  couponClearButton: {
    width: 46,
    height: 46,
    borderRadius: 12,
    backgroundColor: Colors.borderLight,
    alignItems: 'center',
    justifyContent: 'center',
  },
  couponApplied: {
    borderRadius: 12,
    backgroundColor: '#ECFDF5',
    padding: 10,
    gap: 2,
  },
  couponAppliedTitle: {
    fontSize: 13,
    fontWeight: '800',
    color: '#065F46',
  },
  couponAppliedText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#047857',
  },
  pointsBox: {
    gap: 12,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: `${Colors.accent}40`,
    backgroundColor: `${Colors.accent}12`,
    padding: Spacing.md,
  },
  pointsHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  pointsTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: Colors.text,
  },
  pointsSubtitle: {
    marginTop: 2,
    fontSize: 12,
    fontWeight: '600',
    color: Colors.textSecondary,
  },
  pointsRows: {
    gap: 7,
  },
  pointsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
  },
  pointsLabel: {
    fontSize: 13,
    fontWeight: '700',
    color: Colors.textSecondary,
  },
  pointsValue: {
    fontSize: 13,
    fontWeight: '900',
    color: Colors.accentDark,
  },
  pointsHint: {
    fontSize: 12,
    lineHeight: 18,
    color: Colors.textMuted,
    fontWeight: '600',
  },
  rewardsBox: {
    gap: 12,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: `${Colors.primary}22`,
    backgroundColor: `${Colors.primary}0D`,
    padding: Spacing.md,
  },
  rewardsHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  rewardsHeaderCopy: {
    flex: 1,
    minWidth: 0,
  },
  rewardsTitle: { fontSize: 16, fontWeight: '800', color: Colors.text },
  rewardsSubtitle: { marginTop: 2, fontSize: 12, lineHeight: 17, fontWeight: '600', color: Colors.textSecondary },
  rewardsBalance: {
    flexShrink: 0,
    maxWidth: 132,
    fontSize: 18,
    lineHeight: 24,
    fontWeight: '900',
    color: Colors.primary,
    textAlign: 'right',
  },
  rewardsRows: { gap: 7 },
  rewardsRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 12 },
  rewardsLabel: { flex: 1, minWidth: 0, fontSize: 13, fontWeight: '600', color: Colors.textSecondary },
  rewardsValue: { flexShrink: 0, fontSize: 13, fontWeight: '800', color: Colors.text, textAlign: 'right' },
  rewardsWarning: { fontSize: 12, fontWeight: '700', color: Colors.error },
  totalBox: {
    backgroundColor: Colors.surface,
    borderRadius: 12,
    padding: Spacing.md,
  },
  totalRows: { gap: 9 },
  totalRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 12 },
  totalFinalRow: { borderTopWidth: 1, borderTopColor: Colors.border, paddingTop: 10, marginTop: 2 },
  totalLabel: { fontSize: 14, fontWeight: '600', color: Colors.textMuted },
  totalFinalLabel: { fontSize: 14, fontWeight: '800', color: Colors.text },
  totalLineValue: { fontSize: 14, fontWeight: '800', color: Colors.text },
  totalDiscountValue: { fontSize: 14, fontWeight: '900', color: Colors.success },
  totalValue: { fontSize: 20, fontWeight: '800', color: Colors.primary },
  footer: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    padding: Spacing.base,
    paddingBottom: 28,
    backgroundColor: Colors.background,
    borderTopWidth: 1,
    borderTopColor: Colors.border,
    ...Shadows.md,
  },
});
