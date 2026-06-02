import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  TouchableOpacity,
  ScrollView,
  Alert,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useCartStore } from '../../store/cart.store';
import { useUserStore } from '../../store/user.store';
import { createOrder } from '../../services/orders.service';
import { createPaymentIntent } from '../../services/orders.service';
import { Button } from '../../components/ui/Button';
import { OrderTypeSelector } from '../../components/shared/OrderTypeSelector';
import { Colors, Spacing, Typography, Shadows } from '../../theme';

export default function OrderTypeScreen() {
  const router = useRouter();
  const { items, total, tipoPedido, setTipoPedido, restauranteId, clear } = useCartStore();
  const user = useUserStore((s) => s.user);
  const [loading, setLoading] = useState(false);

  async function handleContinue() {
    setLoading(true);
    try {
      // Crear payment intent primero
      const { client_secret, id: intentId } = await createPaymentIntent({
        restaurante_id: restauranteId!,
        amount: total,
        currency: 'mxn',
      });

      router.push({
        pathname: '/checkout/payment',
        params: {
          clientSecret: client_secret,
          intentId,
          restauranteId: String(restauranteId),
          tipoPedido,
        },
      });
    } catch (err) {
      Alert.alert('Error', 'No se pudo iniciar el pago. Intenta de nuevo.');
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
        <Text style={styles.headerTitle}>Tipo de entrega</Text>
        <View style={{ width: 24 }} />
      </View>

      <ScrollView contentContainerStyle={styles.content}>
        <Text style={styles.label}>¿Cómo quieres recibir tu pedido?</Text>

        <View style={styles.selectorRow}>
          <OrderTypeSelector value={tipoPedido} onChange={setTipoPedido} />
        </View>

        {/* Resumen */}
        <View style={styles.summary}>
          <Text style={styles.summaryTitle}>Resumen</Text>
          {items.map((item) => (
            <View key={item.id} style={styles.summaryRow}>
              <Text style={styles.summaryItem} numberOfLines={1}>
                {item.cantidad}x {item.platillo.nombre}
              </Text>
              <Text style={styles.summaryPrice}>${item.subtotal.toFixed(2)}</Text>
            </View>
          ))}
          <View style={styles.divider} />
          <View style={styles.summaryRow}>
            <Text style={styles.totalLabel}>Total</Text>
            <Text style={styles.totalValue}>${total.toFixed(2)}</Text>
          </View>
        </View>
      </ScrollView>

      <View style={styles.footer}>
        <Button
          label="Continuar al pago"
          onPress={handleContinue}
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
  label: { fontSize: 16, fontWeight: '600', color: Colors.text },
  selectorRow: { marginLeft: -Spacing.base, marginRight: -Spacing.base },
  summary: {
    backgroundColor: Colors.surface,
    borderRadius: 14,
    padding: Spacing.md,
    gap: 8,
  },
  summaryTitle: { fontSize: 14, fontWeight: '700', color: Colors.text, marginBottom: 4 },
  summaryRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  summaryItem: { flex: 1, fontSize: 13, color: Colors.textMuted },
  summaryPrice: { fontSize: 13, fontWeight: '600', color: Colors.text },
  divider: { height: 1, backgroundColor: Colors.border, marginVertical: 4 },
  totalLabel: { fontSize: 15, fontWeight: '700', color: Colors.text },
  totalValue: { fontSize: 17, fontWeight: '800', color: Colors.primary },
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
