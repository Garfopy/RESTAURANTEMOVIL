import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  TouchableOpacity,
  Alert,
  ScrollView,
} from 'react-native';
import { useRouter, useLocalSearchParams } from 'expo-router';
import { CardField, useStripe } from '@stripe/stripe-react-native';
import { Ionicons } from '@expo/vector-icons';
import { useCartStore } from '../../store/cart.store';
import { confirmPayment, createOrder } from '../../services/orders.service';
import { Button } from '../../components/ui/Button';
import { Colors, Spacing, Typography, Shadows } from '../../theme';

export default function PaymentScreen() {
  const router = useRouter();
  const { confirmPayment: stripeConfirm } = useStripe();
  const { clientSecret, intentId, restauranteId, tipoPedido } =
    useLocalSearchParams<{
      clientSecret: string;
      intentId: string;
      restauranteId: string;
      tipoPedido: string;
    }>();

  const { items, total, clear } = useCartStore();
  const [loading, setLoading] = useState(false);

  async function handlePay() {
    if (!clientSecret) return;

    setLoading(true);
    try {
      // Confirmar con Stripe
      const { error, paymentIntent } = await stripeConfirm(clientSecret, {
        paymentMethodType: 'Card',
      });

      if (error) {
        Alert.alert('Pago rechazado', error.message);
        return;
      }

      // Crear pedido en backend
      const order = await createOrder({
        restaurante_id: Number(restauranteId),
        tipo_pedido: tipoPedido as never,
        items: items.map((i) => ({
          platillo_id: i.platillo.id,
          cantidad: i.cantidad,
          precio_unit: i.precio_unitario,
          notas: i.notas,
          modificadores: i.modificadores_seleccionados.map((m) => ({
            modificador_id: m.modificador_id,
            opcion_ids: m.opciones.map((o) => o.opcion_id),
          })),
        })),
        payment_intent_id: intentId,
        notas: '',
      });

      // Confirmar en backend
      await confirmPayment({ pedido_id: order.id, payment_intent_id: intentId, metodo: 'card' });

      clear();
      router.replace({ pathname: '/order/[id]', params: { id: String(order.id) } });
    } catch (err) {
      Alert.alert('Error', 'No se pudo completar el pago. Intenta de nuevo.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => router.back()}>
          <Ionicons name="arrow-back" size={24} color={Colors.text} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Pago</Text>
        <View style={{ width: 24 }} />
      </View>

      <ScrollView contentContainerStyle={styles.content}>
        <Text style={styles.sectionLabel}>Datos de tarjeta</Text>
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

        <View style={styles.totalBox}>
          <Text style={styles.totalLabel}>Total a pagar</Text>
          <Text style={styles.totalValue}>${total.toFixed(2)} MXN</Text>
        </View>

        <Text style={styles.secureNote}>
          <Ionicons name="lock-closed-outline" size={12} color={Colors.success} />
          {' '}Pago seguro procesado por Stripe
        </Text>
      </ScrollView>

      <View style={styles.footer}>
        <Button
          label={`Pagar $${total.toFixed(2)}`}
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
  headerTitle: { ...Typography.h3, fontWeight: '700', color: Colors.text },
  content: { padding: Spacing.base, paddingBottom: 120, gap: Spacing.base },
  sectionLabel: { fontSize: 14, fontWeight: '600', color: Colors.text },
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
  secureNote: { fontSize: 12, color: Colors.success, textAlign: 'center' },
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
