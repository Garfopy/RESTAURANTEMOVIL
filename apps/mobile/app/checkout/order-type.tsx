import React, { useMemo, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  Alert,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { getApiError } from '../../services/api';
import { useCartStore } from '../../store/cart.store';
import { useBranchStore } from '../../store/branch.store';
import { requireAuth } from '../../services/auth-gate.service';
import { Button } from '../../components/ui/Button';
import { Colors, Spacing, FontFamily } from '../../theme';

function getSelectedExtras(item: ReturnType<typeof useCartStore.getState>['items'][number]) {
  return item.modificadores_seleccionados.flatMap((mod) =>
    mod.opciones.map((opcion) => ({
      key: `${mod.modificador_id}-${opcion.opcion_id}`,
      nombre: opcion.opcion_nombre,
      precio: Number(opcion.precio_extra || 0),
    }))
  );
}

function getItemCostBreakdown(item: ReturnType<typeof useCartStore.getState>['items'][number]) {
  const selectedExtras = getSelectedExtras(item);
  const baseUnit = Number(item.platillo.precio || 0);
  const extrasUnit = selectedExtras.reduce((sum, extra) => sum + extra.precio, 0);
  const unitTotal = baseUnit + extrasUnit;
  const lineTotal = unitTotal * item.cantidad;

  return {
    selectedExtras,
    baseUnit,
    unitTotal,
    lineTotal,
  };
}

export default function OrderTypeScreen() {
  const router = useRouter();
  const { items, restauranteId } = useCartStore();
  const { sucursales, seleccionada } = useBranchStore();
  const [loading, setLoading] = useState(false);

  const orderTotal = useMemo(
    () => items.reduce((sum, item) => sum + getItemCostBreakdown(item).lineTotal, 0),
    [items]
  );

  const resolvedRestaurantId =
    restauranteId ??
    seleccionada?.id ??
    items[0]?.platillo?.restaurante_id ??
    null;

  const selectedBranch = useMemo(
    () => sucursales.find((s) => String(s.id) === String(resolvedRestaurantId)) ?? seleccionada ?? null,
    [resolvedRestaurantId, seleccionada, sucursales]
  );

  const pickupDetail = selectedBranch
    ? `${selectedBranch.nombre} · ${selectedBranch.direccion || selectedBranch.descripcion || 'Sucursal'}`
    : 'Sucursal seleccionada';

  async function handleContinue() {
    if (!requireAuth(router, {
      message: 'Crea tu cuenta para confirmar el pedido, guardar tus datos y darle seguimiento.',
      returnTo: '/checkout/order-type',
    })) {
      return;
    }

    if (!resolvedRestaurantId || Number.isNaN(Number(resolvedRestaurantId))) {
      Alert.alert('Error', 'No se detectó la sucursal del pedido. Vuelve al menú y selecciona una sucursal antes de pagar.');
      return;
    }

    setLoading(true);
    try {
      router.push({
        pathname: '/checkout/payment',
        params: {
          restauranteId: String(resolvedRestaurantId),
          tipoPedido: 'pickup',
          direccionId: '',
          direccionEntrega: pickupDetail,
        },
      });
    } catch (err) {
      Alert.alert('Error', getApiError(err) || 'No se pudo iniciar el pago. Intenta de nuevo.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backButton}
          onPress={() => router.back()}
          accessibilityLabel="Volver atras"
          accessibilityRole="button"
          testID="back-btn"
        >
          <Ionicons name="arrow-back" size={22} color={Colors.text} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Revisar pedido</Text>
        <View style={{ width: 40 }} />
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.welcomeSection}>
          <Text style={styles.mainLabel}>Revisa tu pedido</Text>
          <Text style={styles.subLabel}>Recoges en sucursal</Text>
        </View>

        <View style={styles.modeSummary}>
          <View style={styles.modeIcon}>
            <Ionicons name="bag-handle-outline" size={26} color={Colors.primary} />
          </View>
          <View style={styles.modeCopy}>
            <Text style={styles.modeTitle}>Pickup</Text>
            <Text style={styles.modeDetail} numberOfLines={2}>{pickupDetail}</Text>
          </View>
        </View>

        <View style={styles.summaryContainer}>
          <Text style={styles.summaryTitle}>Resumen del pedido</Text>
          <View style={styles.ticketBox}>
            {items.map((item) => {
              const {
                selectedExtras,
                baseUnit,
                unitTotal,
                lineTotal,
              } = getItemCostBreakdown(item);

              return (
              <View key={item.id} style={styles.summaryItemBlock}>
                <View style={styles.summaryRow}>
                  <Text style={styles.summaryItem} numberOfLines={1}>
                    <Text style={styles.itemQuantity}>{item.cantidad}x</Text> {item.platillo.nombre}
                  </Text>
                  <Text style={styles.summaryPrice}>${lineTotal.toFixed(2)}</Text>
                </View>

                <View style={styles.summaryBreakdown}>
                  <View style={styles.summaryBreakdownRow}>
                    <Text style={styles.summaryBreakdownLabel}>Plato base</Text>
                    <Text style={styles.summaryBreakdownValue}>${baseUnit.toFixed(2)} c/u</Text>
                  </View>

                  {selectedExtras.map((extra) => (
                    <View key={extra.key} style={styles.summaryBreakdownRow}>
                      <Text style={styles.summaryExtraText} numberOfLines={1}>+ {extra.nombre}</Text>
                      <Text style={styles.summaryExtraPrice}>+${extra.precio.toFixed(2)} c/u</Text>
                    </View>
                  ))}

                  <View style={[styles.summaryBreakdownRow, styles.summaryUnitTotalRow]}>
                    <Text style={styles.summaryUnitTotalLabel}>Total del plato</Text>
                    <Text style={styles.summaryUnitTotalValue}>${unitTotal.toFixed(2)} c/u</Text>
                  </View>
                </View>
              </View>
              );
            })}
            <View style={styles.divider} />
            <View style={styles.totalRow}>
              <Text style={styles.totalLabel}>Total a pagar</Text>
              <Text style={styles.totalValue}>${orderTotal.toFixed(2)} MXN</Text>
            </View>
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
          style={styles.actionButton}
          accessibilityLabel="Continuar al pago"
          testID="checkout-continue-btn"
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
    paddingHorizontal: Spacing.base || 16,
    paddingVertical: 12,
  },
  backButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: Colors.borderLight,
    justifyContent: 'center',
    alignItems: 'center',
  },
  headerTitle: {
    fontFamily: FontFamily.heading,
    fontSize: 19,
    color: Colors.text,
  },
  content: {
    padding: Spacing.base || 16,
    paddingBottom: 140,
    gap: 24,
  },
  welcomeSection: {
    gap: 4,
    marginTop: 8,
  },
  mainLabel: {
    fontSize: 24,
    fontWeight: '800',
    color: Colors.text,
  },
  subLabel: {
    fontSize: 15,
    color: Colors.textMuted,
  },
  modeSummary: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    gap: 12,
    borderRadius: 16,
    backgroundColor: Colors.surfaceElevated,
    borderWidth: 1,
    borderColor: Colors.borderLight,
  },
  modeIcon: {
    width: 50,
    height: 50,
    borderRadius: 25,
    backgroundColor: Colors.surface,
    justifyContent: 'center',
    alignItems: 'center',
  },
  modeCopy: {
    flex: 1,
    minWidth: 0,
  },
  modeTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: Colors.text,
  },
  modeDetail: {
    fontSize: 13,
    color: Colors.textMuted,
    marginTop: 3,
    lineHeight: 18,
  },
  summaryContainer: {
    gap: 12,
  },
  summaryTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: Colors.text,
    marginLeft: 4,
  },
  ticketBox: {
    backgroundColor: Colors.surfaceElevated,
    borderRadius: 16,
    padding: 18,
    borderWidth: 1,
    borderColor: Colors.borderLight,
  },
  summaryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 6,
  },
  summaryItemBlock: {
    paddingVertical: 4,
  },
  summaryItem: {
    flex: 1,
    fontSize: 14,
    color: Colors.textSecondary,
    fontWeight: '500',
  },
  itemQuantity: {
    fontWeight: '700',
    color: Colors.primary,
  },
  summaryPrice: {
    fontSize: 14,
    fontWeight: '600',
    color: Colors.text,
  },
  summaryBreakdown: {
    marginLeft: 25,
    gap: 3,
    paddingRight: 8,
    paddingTop: 2,
    paddingBottom: 6,
  },
  summaryBreakdownRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  summaryBreakdownLabel: {
    fontSize: 12,
    color: Colors.textMuted,
    fontWeight: '600',
  },
  summaryBreakdownValue: {
    fontSize: 12,
    color: Colors.textMuted,
    fontWeight: '700',
  },
  summaryExtraText: {
    flex: 1,
    fontSize: 12,
    color: Colors.textMuted,
    fontWeight: '700',
  },
  summaryExtraPrice: {
    fontSize: 12,
    color: Colors.textMuted,
    fontWeight: '800',
  },
  summaryUnitTotalRow: {
    marginTop: 2,
  },
  summaryUnitTotalLabel: {
    fontSize: 12,
    color: Colors.text,
    fontWeight: '800',
  },
  summaryUnitTotalValue: {
    fontSize: 12,
    color: Colors.text,
    fontWeight: '900',
  },
  divider: {
    height: 1,
    backgroundColor: Colors.border,
    marginVertical: 12,
  },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 4,
  },
  totalLabel: {
    fontSize: 15,
    fontWeight: '700',
    color: Colors.text,
  },
  totalValue: {
    fontSize: 18,
    fontWeight: '800',
    color: Colors.primary,
  },
  footer: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    paddingHorizontal: Spacing.base || 16,
    paddingBottom: 32,
    paddingTop: 16,
    backgroundColor: Colors.background,
    borderTopWidth: 1,
    borderTopColor: Colors.borderLight,
  },
  actionButton: {
    borderRadius: 14,
  },
});
