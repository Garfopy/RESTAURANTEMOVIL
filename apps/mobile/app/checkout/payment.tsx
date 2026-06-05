import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  TouchableOpacity,
  Alert,
  ScrollView,
  Platform,
} from 'react-native';
import { useRouter, useLocalSearchParams } from 'expo-router';
import { CardField, useStripe } from '@stripe/stripe-react-native';
import { Ionicons } from '@expo/vector-icons';
import { useCartStore } from '../../store/cart.store';
import { confirmPayment, createOrder } from '../../services/orders.service';
import { Button } from '../../components/ui/Button';
import { Colors, Spacing, Typography, Shadows } from '../../theme';

type PaymentMethod = 'card' | 'wallet' | 'cash';

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
  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod>('card');

  const isIOS = Platform.OS === 'ios';
  const walletName = isIOS ? 'Apple Pay' : 'Google Pay';
  const walletIcon = isIOS ? 'logo-apple' : 'logo-google';

  async function handlePay() {
    setLoading(true);
    try {
      if (selectedMethod === 'cash') {
        const order = await createOrderBackend('cash');
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
        Alert.alert('En desarrollo', `La integración con ${walletName} está en proceso.`);
        setLoading(false);
        return;
      }

      } catch (err: any) {
        // 1. Esto te lo mostrará en la pantalla del celular
        Alert.alert('Error Real', err.message || JSON.stringify(err));
        
        // 2. Esto lo imprimirá en tu terminal de la computadora (donde corre Expo)
        console.error("🔴 Error detallado en handlePay:", err);
      } finally {
        setLoading(false);
      }
  }

  async function createOrderBackend(metodo_pago: string) {
    return await createOrder({
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
      payment_intent_id: intentId || undefined,
      notas: `Pago vía: ${metodo_pago}`,
    });
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
        <Text style={styles.sectionLabel}>Selecciona cómo quieres pagar</Text>

        {/* --- CONTENEDOR HORIZONTAL --- */}
        <View style={styles.methodsContainer}>
          
          {/* Tarjeta */}
          <TouchableOpacity
            style={[
              styles.methodCard,
              selectedMethod === 'card' && styles.methodCardActive,
            ]}
            onPress={() => setSelectedMethod('card')}
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

          {/* Wallet (Apple/Google Pay) */}
          <TouchableOpacity
            style={[
              styles.methodCard,
              selectedMethod === 'wallet' && styles.methodCardActive,
            ]}
            onPress={() => setSelectedMethod('wallet')}
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

          {/* Efectivo */}
          <TouchableOpacity
            style={[
              styles.methodCard,
              selectedMethod === 'cash' && styles.methodCardActive,
            ]}
            onPress={() => setSelectedMethod('cash')}
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
  
  // NUEVOS ESTILOS PARA LAS CARDS CUADRADAS EN FILA
  methodsContainer: { 
    flexDirection: 'row', 
    gap: 10, // Espaciado fino entre las tres columnas
    justifyContent: 'space-between',
    width: '100%',
  },
  methodCard: {
    flex: 1,                 // Distribuye el espacio equitativamente (33% aproximado cada una)
    aspectRatio: 1,          // Magia pura: las obliga a mantenerse perfectamente cuadradas
    flexDirection: 'column', // Ícono arriba, texto abajo
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: Colors.surface,
    padding: 8,
    borderRadius: 16,        // Bordes un poco más curvos para un look más moderno
    borderWidth: 2,
    borderColor: '#E5E7EB',  // Borde por defecto sutil
    ...Shadows.sm,
  },
  methodCardActive: {
    borderColor: Colors.primary,
    backgroundColor: `${Colors.primary}08`, // Leve tinte del color primario de fondo
  },
  methodText: {
    fontSize: 12,            // Ajustado para que el texto largo quepa en pantallas chicas
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