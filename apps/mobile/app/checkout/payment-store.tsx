import React, { useRef, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Alert,
  ScrollView,
  Platform,
  Image as RNImage,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Image } from 'expo-image';
import { useRouter, useLocalSearchParams } from 'expo-router';
import { CardField, useStripe } from '@stripe/stripe-react-native';
import { Ionicons } from '@expo/vector-icons';
import { formatImageUrl } from '../../services/api';
import { createStoreOrder } from '../../services/store.service';
import { confirmPayment, createPaymentIntent } from '../../services/orders.service';
import { Button } from '../../components/ui/Button';
import { NATIVE_WALLETS_ENABLED, STRIPE_IS_CONFIGURED } from '../../constants/stripe';
import { Colors, Spacing, Shadows } from '../../theme';

type PaymentMethod = 'card' | 'wallet' | 'cash';
type TipoPedido = 'delivery' | 'pickup';
type PreparedStorePayment = {
  orderId: number;
  clientSecret: string;
  intentId: string;
  amount: number;
  status: string;
  usePoints: boolean;
};

export default function StorePaymentScreen() {
  const router = useRouter();
  const { confirmPayment: stripeConfirm } = useStripe();
  const params = useLocalSearchParams<{
    productId: string;
    productName: string;
    productImage: string;
    productPrice: string;
    quantity: string;
    tipo_pedido?: string;
    direccionId?: string;
    direccionEntrega?: string;
    total: string;
  }>();

  const tipoPedido: TipoPedido = (params.tipo_pedido ?? 'delivery') as TipoPedido;
  const isPickup = tipoPedido === 'pickup';
  const productName = params.productName ?? 'Producto';
  const productImage = params.productImage ?? '';
  const productPrice = parseFloat(params.productPrice ?? '0');
  const quantity = parseInt(params.quantity ?? '1', 10);
  const total = parseFloat(params.total ?? '0');
  const direccionEntrega = params.direccionEntrega ?? '';
  const direccionId = params.direccionId ? parseInt(params.direccionId, 10) : undefined;

  const [loading, setLoading] = useState(false);
  const paymentLockRef = useRef(false);
  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod>(STRIPE_IS_CONFIGURED ? 'card' : 'cash');
  const [preparedPayment, setPreparedPayment] = useState<PreparedStorePayment | null>(null);
  const displayedTotal = preparedPayment?.amount ?? total;
  const serverPriceAdjustment = Math.round((displayedTotal - total) * 100) / 100;

  const isIOS = Platform.OS === 'ios';
  const walletName = isIOS ? 'Apple Pay' : 'Google Pay';
  const walletIcon = isIOS ? 'logo-apple' : 'logo-google';

  async function handlePay() {
    if (paymentLockRef.current) return;
    paymentLockRef.current = true;
    setLoading(true);
    try {
      if (selectedMethod === 'cash') {
        const order = await createStoreOrder({
          product_id: Number(params.productId),
          quantity,
          unit_price: productPrice,
          tipo_pedido: tipoPedido,
          direccion_id: direccionId,
          direccion_entrega: direccionEntrega || undefined,
        });
        router.replace({ pathname: '/order/[id]', params: { id: String(order.id) } } as any);
        return;
      }

      if (selectedMethod === 'card') {
        if (!STRIPE_IS_CONFIGURED) {
          throw new Error('Stripe no esta configurado para este APK. Revisa EXPO_PUBLIC_STRIPE_KEY en EAS.');
        }
        let prepared = preparedPayment;
        if (!prepared) {
          const order = await createStoreOrder({
            product_id: Number(params.productId),
            quantity,
            unit_price: productPrice,
            tipo_pedido: tipoPedido,
            direccion_id: direccionId,
            direccion_entrega: direccionEntrega || undefined,
            subtotal: total,
          });
          const paymentIntent = await createPaymentIntent({
            order_id: order.id,
            amount: Number(order.total),
            currency: 'mxn',
          });
          prepared = {
            orderId: order.id,
            clientSecret: paymentIntent.client_secret,
            intentId: paymentIntent.id,
            amount: paymentIntent.amount,
            status: paymentIntent.status,
            usePoints: Boolean(paymentIntent.use_points),
          };
          setPreparedPayment(prepared);

          if (Math.abs(paymentIntent.amount - total) >= 0.01) {
            Alert.alert(
              'Total actualizado',
              `El total vigente es $${paymentIntent.amount.toFixed(2)} MXN. Revisa el importe actualizado y vuelve a tocar Pagar para confirmarlo.`
            );
            return;
          }
        }

        const { error } = prepared.status === 'succeeded'
          ? { error: undefined }
          : await stripeConfirm(prepared.clientSecret, {
              paymentMethodType: 'Card',
            });

        if (error) {
          Alert.alert('Pago rechazado', error.message);
          setLoading(false);
          return;
        }

        await confirmPayment({
          pedido_id: prepared.orderId,
          payment_intent_id: prepared.intentId,
          metodo: 'card',
        });

        router.replace({ pathname: '/order/[id]', params: { id: String(prepared.orderId) } } as any);
        return;
      }

      if (selectedMethod === 'wallet') {
        Alert.alert('En desarrollo', `La integración con ${walletName} está en proceso.`);
        setLoading(false);
        return;
      }
    } catch (err: any) {
      Alert.alert('Error', err.message || 'No se pudo procesar el pago.');
      console.error('🔴 Error en handlePay store:', err);
    } finally {
      paymentLockRef.current = false;
      setLoading(false);
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => router.back()}>
          <Ionicons name="arrow-back" size={24} color={Colors.text} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Método de Pago</Text>
        <View style={{ width: 24 }} />
      </View>

      <ScrollView contentContainerStyle={styles.content}>
        {/* Store order banner - condicional según modalidad */}
        <View style={styles.storeBanner}>
          <View style={styles.storeBannerIcon}>
            <Ionicons
              name={isPickup ? 'storefront-outline' : 'bicycle-outline'}
              size={20}
              color={Colors.accent}
            />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.storeBannerTitle}>Compra en Tienda Amare</Text>
            <Text style={styles.storeBannerSub}>
              {isPickup ? 'Recoger en sucursal' : 'Envío a domicilio'}
            </Text>
          </View>
        </View>

        {/* Product mini card */}
        <View style={styles.productCard}>
          {productImage ? (
            <Image
              source={{ uri: formatImageUrl(productImage) ?? productImage }}
              style={styles.productImg}
              contentFit="cover"
            />
          ) : (
            <View style={styles.productImgPlaceholder}>
              <Ionicons name="cube-outline" size={20} color={Colors.muted} />
            </View>
          )}
          <View style={{ flex: 1 }}>
            <Text style={styles.productName} numberOfLines={1}>{productName}</Text>
            <Text style={styles.productQty}>{quantity}x · ${productPrice.toFixed(2)} c/u</Text>
          </View>
        </View>

        {/* Delivery address - solo si es delivery */}
        {!isPickup && direccionEntrega ? (
          <View style={styles.addressCard}>
            <Ionicons name="location" size={16} color={Colors.primary} />
            <Text style={styles.addressText} numberOfLines={2}>{direccionEntrega}</Text>
          </View>
        ) : null}

        {/* Pickup info */}
        {isPickup && (
          <View style={styles.pickupCard}>
            <Ionicons name="storefront-outline" size={16} color={Colors.success} />
            <Text style={styles.pickupText}>
              Recoge tu producto en la sucursal después del pago
            </Text>
          </View>
        )}

        <Text style={styles.sectionLabel}>Selecciona cómo quieres pagar</Text>

        {/* Payment methods */}
        <View style={styles.methodsContainer}>
          {STRIPE_IS_CONFIGURED ? (
            <TouchableOpacity
              style={[styles.methodCard, selectedMethod === 'card' && styles.methodCardActive]}
              onPress={() => setSelectedMethod('card')}
              disabled={preparedPayment !== null}
            >
              <Ionicons
                name="card-outline"
                size={26}
                color={selectedMethod === 'card' ? Colors.primary : Colors.textMuted}
              />
              <Text
                numberOfLines={2}
                style={[styles.methodText, selectedMethod === 'card' && styles.methodTextActive]}
              >
                Tarjeta
              </Text>
            </TouchableOpacity>
          ) : null}

          {NATIVE_WALLETS_ENABLED ? (
            <TouchableOpacity
              style={[styles.methodCard, selectedMethod === 'wallet' && styles.methodCardActive]}
              onPress={() => setSelectedMethod('wallet')}
              disabled={preparedPayment !== null}
            >
              <Ionicons
                name={walletIcon}
                size={26}
                color={selectedMethod === 'wallet' ? Colors.primary : Colors.textMuted}
              />
              <Text
                numberOfLines={2}
                style={[styles.methodText, selectedMethod === 'wallet' && styles.methodTextActive]}
              >
                {walletName}
              </Text>
            </TouchableOpacity>
          ) : null}

          <TouchableOpacity
            style={[styles.methodCard, selectedMethod === 'cash' && styles.methodCardActive]}
            onPress={() => setSelectedMethod('cash')}
            disabled={preparedPayment !== null}
          >
            <Ionicons
              name="cash-outline"
              size={26}
              color={selectedMethod === 'cash' ? Colors.primary : Colors.textMuted}
            />
            <Text
              numberOfLines={2}
              style={[styles.methodText, selectedMethod === 'cash' && styles.methodTextActive]}
            >
              Efectivo en caja
            </Text>
          </TouchableOpacity>
        </View>

        {/* Card field */}
        {selectedMethod === 'card' && STRIPE_IS_CONFIGURED && (
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
              <Ionicons name="lock-closed-outline" size={12} color={Colors.success} />
              {' '}Pago seguro procesado por Stripe
            </Text>
          </View>
        )}

        <View style={styles.totalBox}>
          <Text style={styles.totalLabel}>Total a pagar</Text>
          {Math.abs(serverPriceAdjustment) >= 0.01 ? (
            <Text style={styles.totalAdjustment}>
              Precio actualizado por el servidor: {serverPriceAdjustment > 0 ? '+' : '-'}${Math.abs(serverPriceAdjustment).toFixed(2)}
            </Text>
          ) : null}
          <Text style={styles.totalValue}>${displayedTotal.toFixed(2)} MXN</Text>
        </View>
      </ScrollView>

      <View style={styles.footer}>
        <Button
          label={
            selectedMethod === 'cash'
              ? `Confirmar pedido ($${displayedTotal.toFixed(2)})`
              : `Pagar $${displayedTotal.toFixed(2)}`
          }
          onPress={handlePay}
          fullWidth
          size="lg"
          loading={loading}
        />
      </View>
    </SafeAreaView>
  );
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
  headerTitle: { fontSize: 18, fontWeight: '700', color: Colors.text },
  content: { padding: Spacing.base, paddingBottom: 120, gap: Spacing.base },

  // Store banner
  storeBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFF7E6',
    borderRadius: 12,
    padding: 12,
    borderWidth: 1,
    borderColor: '#F5C060',
    gap: 10,
  },
  storeBannerIcon: {
    width: 38,
    height: 38,
    borderRadius: 19,
    backgroundColor: '#FFF',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#F5C060',
  },
  storeBannerTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: Colors.text,
  },
  storeBannerSub: {
    fontSize: 12,
    color: Colors.textSecondary,
    marginTop: 2,
  },

  // Product card
  productCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.surface,
    borderRadius: 12,
    padding: 10,
    borderWidth: 1,
    borderColor: Colors.border,
    gap: 10,
  },
  productImg: {
    width: 48,
    height: 48,
    borderRadius: 8,
    backgroundColor: '#F3F4F6',
  },
  productImgPlaceholder: {
    width: 48,
    height: 48,
    borderRadius: 8,
    backgroundColor: '#F3F4F6',
    alignItems: 'center',
    justifyContent: 'center',
  },
  productName: {
    fontSize: 14,
    fontWeight: '700',
    color: Colors.text,
  },
  productQty: {
    fontSize: 12,
    color: Colors.textMuted,
    marginTop: 2,
  },

  // Address (delivery)
  addressCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F0F4FF',
    borderRadius: 10,
    padding: 10,
    gap: 8,
  },
  addressText: {
    flex: 1,
    fontSize: 13,
    color: Colors.primary,
    fontWeight: '500',
  },

  // Pickup info
  pickupCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#ECFDF5',
    borderRadius: 10,
    padding: 12,
    gap: 8,
    borderWidth: 1,
    borderColor: '#A7F3D0',
  },
  pickupText: {
    flex: 1,
    fontSize: 13,
    color: '#065F46',
    fontWeight: '500',
  },

  sectionLabel: { fontSize: 16, fontWeight: '600', color: Colors.text, marginBottom: 4 },

  methodsContainer: {
    flexDirection: 'row',
    gap: 10,
    justifyContent: 'space-between',
    width: '100%',
  },
  methodCard: {
    flex: 1,
    aspectRatio: 1,
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
  totalBox: {
    gap: 4,
    backgroundColor: Colors.surface,
    borderRadius: 12,
    padding: Spacing.md,
  },
  totalLabel: { fontSize: 14, fontWeight: '600', color: Colors.textMuted },
  totalAdjustment: { fontSize: 12, fontWeight: '700', color: Colors.warning },
  totalValue: { fontSize: 20, fontWeight: '800', color: Colors.primary, alignSelf: 'flex-end' },
  secureNote: { fontSize: 12, color: Colors.success, textAlign: 'center', marginTop: 4 },
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
