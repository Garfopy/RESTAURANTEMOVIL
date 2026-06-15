import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  TouchableOpacity,
  Alert,
  ScrollView,
  Platform,
  useWindowDimensions,
} from 'react-native';
import { useRouter, useLocalSearchParams } from 'expo-router';
import { CardField, useStripe } from '@stripe/stripe-react-native';
import { Ionicons } from '@expo/vector-icons';
import { useCartStore } from '../../store/cart.store';
import { useBranchStore } from '../../store/branch.store';
import { confirmPayment, createOrder } from '../../services/orders.service';
import { getRestaurantConfig } from '../../services/config.service';
import { getApiError } from '../../services/api';
import { Button } from '../../components/ui/Button';
import { Colors, Spacing, Typography, Shadows } from '../../theme';
import type { RestaurantConfig, MetodoPagoHabilitado } from '@amare/types';

type PaymentMethod = 'card' | 'wallet' | 'cash';

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
  {
    id: 'card',
    label: 'Tarjeta',
    icon: 'card-outline',
    iconActive: 'card',
  },
  {
    id: 'wallet',
    label: walletName,
    icon: walletIcon,
    iconActive: walletIcon,
  },
  {
    id: 'cash',
    label: 'Efectivo',
    icon: 'cash-outline',
    iconActive: 'cash',
  },
];

/** Convierte un MetodoPagoHabilitado de la BD al id de PaymentMethod de la UI */
function dbMethodToUI(m: MetodoPagoHabilitado): PaymentMethod {
  if (m === 'apple_pay' || m === 'google_pay') return 'wallet';
  return m as PaymentMethod;
}

export default function PaymentScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const { confirmPayment: stripeConfirm } = useStripe();
  const { clientSecret, intentId, restauranteId, tipoPedido, direccionId, direccionEntrega } =
    useLocalSearchParams<{
      clientSecret: string;
      intentId: string;
      restauranteId: string;
      tipoPedido: string;
      direccionId?: string;
      direccionEntrega?: string;
    }>();

  const { items, total, clear, restauranteId: cartRestaurantId } = useCartStore();
  const selectedBranchId = useBranchStore((s) => s.seleccionada?.id);
  const resolvedRestaurantId =
    Number(restauranteId) ||
    cartRestaurantId ||
    selectedBranchId ||
    items[0]?.platillo?.restaurante_id ||
    null;
  const [loading, setLoading] = useState(false);
  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod>('card');
  const [config, setConfig] = useState<RestaurantConfig | null>(null);
  const [loadingConfig, setLoadingConfig] = useState(true);

  // Métodos habilitados según la BD, mapeados a los IDs de la UI (deduplicado por si apple_pay y google_pay comparten 'wallet')
  const enabledMethodIds: PaymentMethod[] = config
    ? [...new Set(config.metodos_pago.map(dbMethodToUI))]
    : ['card', 'cash'];

  // Solo mostramos los métodos que están habilitados
  const enabledMethods = ALL_PAYMENT_METHODS.filter((m) =>
    enabledMethodIds.includes(m.id)
  );

  // Cargar configuración al montar
  useEffect(() => {
    if (resolvedRestaurantId) {
      getRestaurantConfig(Number(resolvedRestaurantId))
        .then((cfg) => {
          setConfig(cfg);
          // Seleccionar el primer método disponible si el actual no está habilitado
          const ids = [...new Set(cfg.metodos_pago.map(dbMethodToUI))];
          if (!ids.includes(selectedMethod)) {
            setSelectedMethod(ids[0] ?? 'cash');
          }
        })
        .catch((err) => console.error('Error al cargar configuración:', err))
        .finally(() => setLoadingConfig(false));
    } else {
      setLoadingConfig(false);
    }
  }, [resolvedRestaurantId, selectedMethod]);

  // Cálculo dinámico del ancho de cada card según cuántas hay
  function getCardWidth() {
    const count = enabledMethods.length;
    const containerPadding = Spacing.base * 2;
    const gap = 10;
    const available = width - containerPadding;

    if (count === 1) {
      return Math.min(available * 0.55, 200);
    }
    if (count === 2) {
      return (available - gap) / 2;
    }
    // 3 métodos
    return (available - gap * 2) / 3;
  }

  async function handlePay() {
    setLoading(true);
    try {
      if (!resolvedRestaurantId || Number.isNaN(Number(resolvedRestaurantId))) {
        throw new Error('No se detectó la sucursal del pedido. Regresa al carrito e intenta de nuevo.');
      }

      if (selectedMethod === 'cash') {
        const order = await createOrderBackend('cash');
        await confirmPayment({ pedido_id: order.id, payment_intent_id: intentId ?? '', metodo: 'cash' });
        clear();
        router.replace({ pathname: '/order/[id]', params: { id: String(order.id) } });
        return;
      }

      if (selectedMethod === 'card') {
        if (!clientSecret) throw new Error('Falta el secreto del cliente para procesar la tarjeta.');

        const { error } = await stripeConfirm(clientSecret, {
          paymentMethodType: 'Card',
        });

        if (error) {
          Alert.alert('Pago rechazado', error.message);
          return;
        }

        const order = await createOrderBackend('card');
        await confirmPayment({ pedido_id: order.id, payment_intent_id: intentId, metodo: 'card' });

        clear();
        router.replace({ pathname: '/order/[id]', params: { id: String(order.id) } });
        return;
      }

      if (selectedMethod === 'wallet') {
        const walletMethod = isIOS ? 'apple_pay' : 'google_pay';
        const order = await createOrderBackend(walletMethod);
        // TODO: Integrar Apple Pay / Google Pay con Stripe o pasarela nativa
        await confirmPayment({ pedido_id: order.id, payment_intent_id: intentId ?? '', metodo: walletMethod });
        clear();
        router.replace({ pathname: '/order/[id]', params: { id: String(order.id) } });
        return;
      }
    } catch (err: any) {
      Alert.alert('Error', getApiError(err) || err.message || 'No se pudo procesar el pago.');
      console.error('🔴 Error detallado en handlePay:', err);
    } finally {
      setLoading(false);
    }
  }

  async function createOrderBackend(metodo_pago: string) {
    return await createOrder({
      restaurante_id: Number(resolvedRestaurantId),
      tipo_pedido: tipoPedido as never,
      direccion_id: typeof direccionId === 'string' && direccionId !== '' ? Number(direccionId) : undefined,
      direccion_entrega: typeof direccionEntrega === 'string' && direccionEntrega !== '' ? direccionEntrega : undefined,
      items: items.map((i) => ({
        platillo_id: i.platillo.id,
        cantidad: i.cantidad,
        precio_unit: i.precio_unitario,
        notas: i.notas,
        modificadores: i.modificadores_seleccionados.map((m) => ({
          modificador_id: m.modificador_id,
          modificador_nombre: m.modificador_nombre,
          opciones: m.opciones.map((o) => ({
            opcion_id: o.opcion_id,
            opcion_nombre: o.opcion_nombre,
            precio_extra: o.precio_extra,
          })),
        })),
      })),
      payment_intent_id: intentId || undefined,
      notas: `Pago vía: ${metodo_pago}`,
    });
  }

  const cardWidth = getCardWidth();
  const count = enabledMethods.length;

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity
          onPress={() => router.back()}
          accessibilityLabel="Volver atrás"
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

        {/* --- CONTENEDOR DE MÉTODOS DINÁMICO --- */}
        <View style={[
          styles.methodsContainer,
          count === 1 && styles.methodsContainerCentered,
        ]}>
          {enabledMethods.map((method) => {
            const isSelected = selectedMethod === method.id;
            return (
              <TouchableOpacity
                key={method.id}
                style={[
                  styles.methodCard,
                  { width: cardWidth, aspectRatio: count === 1 ? 1.2 : 1 },
                  isSelected && styles.methodCardActive,
                ]}
                onPress={() => setSelectedMethod(method.id)}
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
                <Text
                  numberOfLines={2}
                  style={[styles.methodText, isSelected && styles.methodTextActive]}
                >
                  {method.label}
                </Text>
              </TouchableOpacity>
            );
          })}
        </View>

        {/* --- FORMULARIO DE TARJETA --- */}
        {selectedMethod === 'card' && (
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
          <Text style={styles.totalValue}>${total.toFixed(2)} MXN</Text>
        </View>
      </ScrollView>

      <View style={styles.footer}>
        <Button
          label={selectedMethod === 'cash' ? `Confirmar pedido ($${total.toFixed(2)})` : `Pagar $${total.toFixed(2)}`}
          onPress={handlePay}
          fullWidth
          size="lg"
          loading={loading}
          accessibilityLabel={selectedMethod === 'cash' ? `Confirmar pedido por $${total.toFixed(2)} en efectivo` : `Pagar $${total.toFixed(2)} con ${selectedMethod === 'card' ? 'tarjeta' : 'billetera digital'}`}
          testID="payment-confirm-btn"
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
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: Colors.surface,
    borderRadius: 12,
    padding: Spacing.md,
  },
  totalLabel: { fontSize: 14, fontWeight: '600', color: Colors.textMuted },
  totalValue: { fontSize: 20, fontWeight: '800', color: Colors.primary },
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
