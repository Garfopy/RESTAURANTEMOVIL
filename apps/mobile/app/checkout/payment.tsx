import React, { useEffect, useState } from 'react';
import {
  Alert,
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
import { CardField, useStripe } from '@stripe/stripe-react-native';
import { useQueryClient } from '@tanstack/react-query';
import { Ionicons } from '@expo/vector-icons';
import { useCartStore } from '../../store/cart.store';
import { useBranchConfigStore, useBranchStore } from '../../store/branch.store';
import { useTableSessionStore } from '../../store/table-session.store';
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
import { tableSessionKeys } from '../../services/table-session.service';
import { Button } from '../../components/ui/Button';
import { InvoiceRequestForm } from '../../components/shared/InvoiceRequestForm';
import { Colors, Shadows, Spacing, Typography } from '../../theme';
import type { MetodoPagoHabilitado } from '@amare/types';

type PaymentMethod = 'card' | 'wallet' | 'cash' | 'amare';

interface PaymentMethodDef {
  id: PaymentMethod;
  label: string;
  icon: string;
  iconActive: string;
}

const isIOS = Platform.OS === 'ios';
const walletName = isIOS ? 'Apple Pay' : 'Google Pay';
const walletIcon = isIOS ? 'logo-apple' : 'logo-google';

const ALL_PAYMENT_METHODS: PaymentMethodDef[] = [
  { id: 'card', label: 'Tarjeta', icon: 'card-outline', iconActive: 'card' },
  { id: 'wallet', label: walletName, icon: walletIcon, iconActive: walletIcon },
  { id: 'cash', label: 'Efectivo', icon: 'cash-outline', iconActive: 'cash' },
  { id: 'amare', label: 'Saldo Amare', icon: 'sparkles-outline', iconActive: 'sparkles' },
];

function dbMethodToUI(m: MetodoPagoHabilitado): PaymentMethod {
  if (m === 'apple_pay' || m === 'google_pay') return 'wallet';
  return m as PaymentMethod;
}

export default function PaymentScreen() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const { width } = useWindowDimensions();
  const { confirmPayment: stripeConfirm } = useStripe();
  const {
    clientSecret,
    intentId,
    restauranteId,
    tipoPedido,
    direccionId,
    direccionEntrega,
    mesaId,
    mesaLabel,
    orderId,
    amount,
    folio,
  } = useLocalSearchParams<{
    clientSecret: string;
    intentId: string;
    restauranteId: string;
    tipoPedido: string;
    direccionId?: string;
    direccionEntrega?: string;
    mesaId?: string;
    mesaLabel?: string;
    orderId?: string;
    amount?: string;
    folio?: string;
  }>();

  const { items, total, clear, restauranteId: cartRestaurantId } = useCartStore();
  const selectedBranchId = useBranchStore((s) => s.seleccionada?.id);
  const tableSession = useTableSessionStore((s) => s.session);
  const resolvedRestaurantId =
    Number(restauranteId) ||
    cartRestaurantId ||
    selectedBranchId ||
    items[0]?.platillo?.restaurante_id ||
    null;

  const existingOrderId = typeof orderId === 'string' && orderId !== '' ? Number(orderId) : null;
  const parsedAmount = typeof amount === 'string' && amount !== '' ? Number(amount) : NaN;
  const paymentAmount = Number.isFinite(parsedAmount) && parsedAmount > 0 ? parsedAmount : total;
  const routeMesaId = typeof mesaId === 'string' && mesaId !== '' ? Number(mesaId) : null;
  const resolvedMesaId =
    routeMesaId !== null && Number.isFinite(routeMesaId)
      ? routeMesaId
      : tipoPedido === 'eat_in'
        ? tableSession?.mesaId
        : undefined;
  const resolvedMesaLabel =
    typeof mesaLabel === 'string' && mesaLabel !== ''
      ? mesaLabel
      : tipoPedido === 'eat_in'
        ? tableSession?.mesaLabel ?? ''
        : '';
  const [loading, setLoading] = useState(false);
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

  const config = useBranchConfigStore((state) => (state.branchId === resolvedRestaurantId ? state.config : null));
  const refreshBranchConfig = useBranchConfigStore((state) => state.refresh);
  const invoiceEnabled = Boolean(config?.facturacion?.habilitada);

  const enabledMethodIds: PaymentMethod[] = config
    ? [...new Set<PaymentMethod>([...config.metodos_pago.map(dbMethodToUI), 'amare'])]
    : ['card', 'cash', 'amare'];

  const enabledMethods = ALL_PAYMENT_METHODS.filter((method) => enabledMethodIds.includes(method.id));

  useEffect(() => {
    if (!resolvedRestaurantId) return;
    void refreshBranchConfig(Number(resolvedRestaurantId)).catch((err) =>
      console.error('Error al cargar configuracion:', err)
    );
  }, [refreshBranchConfig, resolvedRestaurantId]);

  useEffect(() => {
    if (!config) return;
    const ids = [...new Set<PaymentMethod>([...config.metodos_pago.map(dbMethodToUI), 'amare'])];
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
    let cancelled = false;

    async function loadRewards() {
      try {
        const wallet = await getRewardsWallet();
        if (!cancelled) setRewardsWallet(wallet);
      } catch (error) {
        console.warn('No se pudo cargar Saldo Amare', error);
      }
    }

    void loadRewards();
    return () => {
      cancelled = true;
    };
  }, []);

  const rewardsPaymentMode = selectedMethod === 'amare' ? 'wallet' : 'external';
  const couponDiscount = Math.max(0, Number(couponQuote?.discount ?? 0));
  const promoAdjustedAmount = Math.max(0, Math.round((paymentAmount - couponDiscount) * 100) / 100);

  useEffect(() => {
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
        console.warn('No se pudo cotizar Saldo Amare', error);
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
  const pointsApplied = useRewardsPoints ? Math.min(availablePoints, Math.floor(totalAfterMethodDiscount)) : 0;
  const pointsDiscount = pointsApplied;
  const effectivePaymentAmount = Math.max(0, Math.round((totalAfterMethodDiscount - pointsDiscount) * 100) / 100);
  const pointsEarned = selectedMethod === 'amare' ? 0 : Math.max(0, Math.round(effectivePaymentAmount * 0.05));
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
    setLoading(true);
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

      if (selectedMethod === 'cash') {
        const order = existingOrderId ? null : await createOrderBackend('cash');
        const targetOrderId = existingOrderId ?? order!.id;
        const confirmation = await confirmPayment({
          pedido_id: targetOrderId,
          payment_intent_id: intentId ?? '',
          metodo: 'cash',
          use_points: useRewardsPoints,
          promo_code: couponQuote?.code ?? (couponCode.trim() || undefined),
          invoice_request: invoiceRequest,
        });
        if (!existingOrderId) clear();
        await refreshRewardsWallet();
        showInvoiceReceived(invoiceRequest !== null);
        await finishOrderFlow(targetOrderId, confirmation.exit_pass, order?.folio);
        return;
      }

      if (selectedMethod === 'amare') {
        if (!canPayWithAmare) {
          throw new Error('Tu Saldo Amare no alcanza para cubrir este pago.');
        }
        const order = existingOrderId ? null : await createOrderBackend('amare_wallet');
        const targetOrderId = existingOrderId ?? order!.id;
        const confirmation = await confirmPayment({
          pedido_id: targetOrderId,
          metodo: 'amare_wallet',
          use_points: useRewardsPoints,
          promo_code: couponQuote?.code ?? (couponCode.trim() || undefined),
          invoice_request: invoiceRequest,
        });
        if (!existingOrderId) clear();
        await refreshRewardsWallet();
        showInvoiceReceived(invoiceRequest !== null);
        await finishOrderFlow(targetOrderId, confirmation.exit_pass, order?.folio);
        return;
      }

      if (selectedMethod === 'card') {
        const paymentIntent = await resolvePaymentIntent(effectivePaymentAmount);
        const order = existingOrderId ? null : await createOrderBackend('card', paymentIntent.intentId);
        const targetOrderId = existingOrderId ?? order!.id;
        const { error } = await stripeConfirm(paymentIntent.clientSecret, {
          paymentMethodType: 'Card',
        });

        if (error) {
          Alert.alert('Pago rechazado', error.message);
          return;
        }

        const confirmation = await confirmPayment({
          pedido_id: targetOrderId,
          payment_intent_id: paymentIntent.intentId,
          metodo: 'card',
          use_points: useRewardsPoints,
          promo_code: couponQuote?.code ?? (couponCode.trim() || undefined),
          invoice_request: invoiceRequest,
        });
        if (!existingOrderId) clear();
        await refreshRewardsWallet();
        showInvoiceReceived(invoiceRequest !== null);
        await finishOrderFlow(targetOrderId, confirmation.exit_pass, order?.folio);
        return;
      }

      if (selectedMethod === 'wallet') {
        const paymentIntent = await resolvePaymentIntent(effectivePaymentAmount);
        const walletMethod = isIOS ? 'apple_pay' : 'google_pay';
        const order = existingOrderId ? null : await createOrderBackend(walletMethod, paymentIntent.intentId);
        const targetOrderId = existingOrderId ?? order!.id;
        const confirmation = await confirmPayment({
          pedido_id: targetOrderId,
          payment_intent_id: paymentIntent.intentId,
          metodo: walletMethod,
          use_points: useRewardsPoints,
          promo_code: couponQuote?.code ?? (couponCode.trim() || undefined),
          invoice_request: invoiceRequest,
        });
        if (!existingOrderId) clear();
        await refreshRewardsWallet();
        showInvoiceReceived(invoiceRequest !== null);
        await finishOrderFlow(targetOrderId, confirmation.exit_pass, order?.folio);
      }
    } catch (err: any) {
      Alert.alert('Error', getApiError(err) || err.message || 'No se pudo procesar el pago.');
      console.error('Error detallado en handlePay:', err);
    } finally {
      setLoading(false);
    }
  }

  async function refreshRewardsWallet() {
    const wallet = await getRewardsWallet().catch(() => null);
    if (wallet) {
      setRewardsWallet(wallet);
    }
  }

  async function finishOrderFlow(targetOrderId: number, exitPass: any, orderFolio?: string | null) {
    refreshRealtimeState(queryClient);

    if (tipoPedido === 'eat_in' && exitPass) {
      router.replace({
        pathname: '/checkout/exit-pass',
        params: {
          orderId: String(targetOrderId),
          payload: exitPass.payload,
          folio: exitPass.folio || folio || orderFolio || '',
          mesaLabel: resolvedMesaLabel,
        },
      });
      return;
    }

    router.replace({ pathname: '/order/[id]', params: { id: String(targetOrderId) } });
  }

  async function resolvePaymentIntent(amountToCharge: number): Promise<{ clientSecret: string; intentId: string }> {
    if (clientSecret && intentId && Math.abs(amountToCharge - paymentAmount) < 0.01) {
      return { clientSecret, intentId };
    }

    const shouldPriceItemsOnServer = Math.abs(amountToCharge - paymentAmount) < 0.01;
    const paymentIntent = await createPaymentIntent({
      order_id: existingOrderId ?? undefined,
      amount: amountToCharge,
      currency: 'mxn',
      restaurante_id: existingOrderId || !shouldPriceItemsOnServer ? undefined : Number(resolvedRestaurantId),
      items: existingOrderId || !shouldPriceItemsOnServer
        ? undefined
        : items.map((item) => ({
            product_id: item.platillo.id,
            quantity: item.cantidad,
            origen: 'menu',
            modificadores: item.modificadores_seleccionados,
          })),
    });

    return {
      clientSecret: paymentIntent.client_secret,
      intentId: paymentIntent.id,
    };
  }

  async function createOrderBackend(metodoPago: string, paymentIntentId?: string) {
    return await createOrder({
      restaurante_id: Number(resolvedRestaurantId),
      tipo_pedido: tipoPedido as never,
      direccion_id: typeof direccionId === 'string' && direccionId !== '' ? Number(direccionId) : undefined,
      direccion_entrega:
        typeof direccionEntrega === 'string' && direccionEntrega !== '' ? direccionEntrega : undefined,
      mesa_id: resolvedMesaId,
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
      payment_intent_id: paymentIntentId || intentId || undefined,
      promo_code: couponQuote?.code ?? undefined,
      notas: `Pago via: ${metodoPago}`,
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

      <ScrollView contentContainerStyle={styles.content}>
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
                disabled={isAmareDisabled}
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

        {selectedMethod === 'card' ? (
          <View style={styles.stripeContainer}>
            <Text style={styles.sectionLabel}>Datos de la tarjeta</Text>
            <CardField
              postalCodeEnabled={false}
              placeholders={{ number: '1234 5678 9012 3456' }}
              cardStyle={{
                backgroundColor: Colors.surface,
                textColor: Colors.text,
                placeholderColor: Colors.textMuted,
                borderRadius: 12,
              }}
              style={styles.cardField}
            />
            <Text style={styles.secureNote}>
              <Ionicons name="lock-closed-outline" size={12} color={Colors.success} /> Pago seguro procesado por Stripe
            </Text>
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
                editable={!couponLoading}
              />
              {couponQuote ? (
                <TouchableOpacity style={styles.couponClearButton} onPress={clearCoupon}>
                  <Ionicons name="close" size={18} color={Colors.textMuted} />
                </TouchableOpacity>
              ) : (
                <TouchableOpacity style={styles.couponButton} onPress={() => void applyCoupon()} disabled={couponLoading}>
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

        <View style={styles.pointsBox}>
          <View style={styles.pointsHeader}>
            <View style={{ flex: 1 }}>
              <Text style={styles.pointsTitle}>Puntos Amare</Text>
              <Text style={styles.pointsSubtitle}>1 punto = 1 peso. Puedes activarlos o quitarlos para este pedido.</Text>
            </View>
            {availablePoints > 0 ? (
              <Switch
                value={useRewardsPoints}
                onValueChange={setUseRewardsPoints}
                trackColor={{ false: '#D1D5DB', true: '#A7F3D0' }}
                thumbColor={useRewardsPoints ? '#059669' : '#F9FAFB'}
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
            <Text style={styles.pointsHint}>Con Saldo Amare obtienes 10% de descuento directo.</Text>
          ) : (
            <Text style={styles.pointsHint}>Si no pagas con Saldo Amare, recibes 5% del total pagado en puntos.</Text>
          )}
        </View>

        {selectedMethod === 'amare' ? (
          <View style={styles.rewardsBox}>
            <View style={styles.rewardsHeader}>
              <View style={styles.rewardsHeaderCopy}>
                <Text style={styles.rewardsTitle}>Saldo Amare</Text>
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
                <Text style={styles.rewardsLabel}>Descuento Amare</Text>
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
                <Text style={styles.rewardsValue}>${effectivePaymentAmount.toFixed(2)}</Text>
              </View>
            </View>

            {!canPayWithAmare ? (
              <Text style={styles.rewardsWarning}>Tu Saldo Amare no alcanza para cubrir este pago.</Text>
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
                <Text style={styles.totalLabel}>Descuento Amare</Text>
                <Text style={styles.totalDiscountValue}>-${methodDiscount.toFixed(2)}</Text>
              </View>
            ) : null}
            {pointsDiscount > 0 ? (
              <View style={styles.totalRow}>
                <Text style={styles.totalLabel}>Puntos</Text>
                <Text style={styles.totalDiscountValue}>-${pointsDiscount.toFixed(2)}</Text>
              </View>
            ) : null}
            <View style={[styles.totalRow, styles.totalFinalRow]}>
              <Text style={styles.totalFinalLabel}>Total a pagar</Text>
              <Text style={styles.totalValue}>${effectivePaymentAmount.toFixed(2)} MXN</Text>
            </View>
          </View>
        </View>
      </ScrollView>

      <View style={styles.footer}>
        <Button
          label={
            selectedMethod === 'cash'
              ? `Confirmar pago ($${effectivePaymentAmount.toFixed(2)})`
              : selectedMethod === 'amare'
                ? `Pagar con saldo ($${effectivePaymentAmount.toFixed(2)})`
                : `Pagar $${effectivePaymentAmount.toFixed(2)}`
          }
          onPress={handlePay}
          fullWidth
          size="lg"
          loading={loading}
          disabled={selectedMethod === 'amare' && (!canPayWithAmare || rewardsLoading)}
          accessibilityLabel={
            selectedMethod === 'cash'
              ? `Confirmar pago por $${effectivePaymentAmount.toFixed(2)} en efectivo`
              : selectedMethod === 'amare'
                ? `Pagar $${effectivePaymentAmount.toFixed(2)} con Saldo Amare`
                : `Pagar $${effectivePaymentAmount.toFixed(2)} con ${selectedMethod === 'card' ? 'tarjeta' : 'billetera digital'}`
          }
          testID="payment-confirm-btn"
        />
      </View>
    </SafeAreaView>
  );
}

function refreshRealtimeState(queryClient: ReturnType<typeof useQueryClient>): void {
  void queryClient.invalidateQueries({ queryKey: ['orders'] });
  void queryClient.invalidateQueries({ queryKey: ['social'] });
  void queryClient.invalidateQueries({ queryKey: tableSessionKeys.diagnostic });
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
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
    borderColor: '#FED7AA',
    backgroundColor: '#FFF7ED',
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
    color: '#7C2D12',
  },
  pointsSubtitle: {
    marginTop: 2,
    fontSize: 12,
    fontWeight: '600',
    color: '#9A3412',
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
    color: '#9A3412',
  },
  pointsValue: {
    fontSize: 13,
    fontWeight: '900',
    color: '#7C2D12',
  },
  pointsHint: {
    fontSize: 12,
    lineHeight: 18,
    color: '#9A3412',
    fontWeight: '600',
  },
  rewardsBox: {
    gap: 12,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#D1FAE5',
    backgroundColor: '#F0FDF4',
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
  rewardsTitle: { fontSize: 16, fontWeight: '800', color: '#064E3B' },
  rewardsSubtitle: { marginTop: 2, fontSize: 12, lineHeight: 17, fontWeight: '600', color: '#047857' },
  rewardsBalance: {
    flexShrink: 0,
    maxWidth: 132,
    fontSize: 18,
    lineHeight: 24,
    fontWeight: '900',
    color: '#065F46',
    textAlign: 'right',
  },
  rewardsRows: { gap: 7 },
  rewardsRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 12 },
  rewardsLabel: { flex: 1, minWidth: 0, fontSize: 13, fontWeight: '600', color: '#047857' },
  rewardsValue: { flexShrink: 0, fontSize: 13, fontWeight: '800', color: '#064E3B', textAlign: 'right' },
  rewardsWarning: { fontSize: 12, fontWeight: '700', color: Colors.error || '#DC2626' },
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
